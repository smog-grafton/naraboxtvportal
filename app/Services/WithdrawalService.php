<?php

namespace App\Services;

use App\Models\CreatorEarning;
use App\Models\CreatorPayoutAttempt;
use App\Models\CreatorPayoutMethod;
use App\Models\CreatorWithdrawalAllocation;
use App\Models\CreatorWithdrawalRequest;
use App\Models\FinancialSetting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class WithdrawalService
{
    public function __construct(
        private CreatorEarningsService $earningsService,
        private IoTeCService $iotecService
    ) {
    }

    /**
     * Request a withdrawal. Validates balance, min threshold, no duplicate pending.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function requestWithdrawal(
        \App\Models\User $user,
        CreatorPayoutMethod $payoutMethod,
        float $amount
    ): CreatorWithdrawalRequest {
        $settings = FinancialSetting::current();
        if (!$settings) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'amount' => ['Financial settings are not configured.'],
            ]);
        }

        $minAmount = (float) $settings->min_withdrawal_amount;
        if ($amount < $minAmount) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'amount' => ["Minimum withdrawal amount is " . number_format($minAmount) . " UGX."],
            ]);
        }

        if ($payoutMethod->user_id !== $user->id) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'payout_method' => ['Invalid payout method.'],
            ]);
        }

        $balance = $this->earningsService->getBalance($user);
        if ($balance['available'] < $amount) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'amount' => ['Insufficient available balance.'],
            ]);
        }

        $hasPending = CreatorWithdrawalRequest::where('user_id', $user->id)
            ->whereIn('status', [
                CreatorWithdrawalRequest::STATUS_PENDING,
                CreatorWithdrawalRequest::STATUS_UNDER_REVIEW,
                CreatorWithdrawalRequest::STATUS_APPROVED,
                CreatorWithdrawalRequest::STATUS_PROCESSING,
            ])
            ->exists();

        if ($hasPending) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'amount' => ['You already have a withdrawal in progress.'],
            ]);
        }

        return CreatorWithdrawalRequest::create([
            'user_id' => $user->id,
            'payout_method_id' => $payoutMethod->id,
            'amount' => $amount,
            'status' => CreatorWithdrawalRequest::STATUS_PENDING,
            'reference' => 'WDR-' . strtoupper(Str::random(12)),
            'requested_at' => now(),
        ]);
    }

    public function approve(CreatorWithdrawalRequest $request, \App\Models\User $adminUser): void
    {
        if ($request->status !== CreatorWithdrawalRequest::STATUS_PENDING
            && $request->status !== CreatorWithdrawalRequest::STATUS_UNDER_REVIEW) {
            return;
        }

        $request->update([
            'status' => CreatorWithdrawalRequest::STATUS_APPROVED,
            'approved_by' => $adminUser->id,
            'approved_at' => now(),
        ]);
    }

    /**
     * Process the withdrawal: allocate earnings, then dispatch to gateway.
     */
    public function process(CreatorWithdrawalRequest $request): void
    {
        if ($request->status !== CreatorWithdrawalRequest::STATUS_APPROVED) {
            return;
        }

        DB::transaction(function () use ($request) {
            $this->allocateEarnings($request);
            $request->update(['status' => CreatorWithdrawalRequest::STATUS_PROCESSING]);
        });

        $settings = FinancialSetting::current();
        $method = $request->payoutMethod;
        if ($settings?->iotec_disbursement_enabled && $method) {
            if ($method->method_type === 'mobile_money') {
                $this->processViaIoTec($request);
            } elseif ($method->method_type === 'bank') {
                $this->processViaIoTecBank($request);
            } else {
                $this->markFailed($request, 'Unsupported payout method type.');
            }
        } elseif ($settings?->pawapay_disbursement_enabled) {
            $this->processViaPawaPay($request);
        } else {
            $this->markManualPending($request);
        }
    }

    private function allocateEarnings(CreatorWithdrawalRequest $request): void
    {
        $amountNeeded = (float) $request->amount;
        $earnings = CreatorEarning::forUser($request->user_id)
            ->available()
            ->orderBy('available_at')
            ->orderBy('id')
            ->get();

        $allocated = 0;
        foreach ($earnings as $earning) {
            if ($allocated >= $amountNeeded) {
                break;
            }
            $existingAllocated = CreatorWithdrawalAllocation::where('creator_earning_id', $earning->id)
                ->whereHas('withdrawalRequest', fn ($q) => $q->whereIn('status', [
                    CreatorWithdrawalRequest::STATUS_PENDING,
                    CreatorWithdrawalRequest::STATUS_UNDER_REVIEW,
                    CreatorWithdrawalRequest::STATUS_APPROVED,
                    CreatorWithdrawalRequest::STATUS_PROCESSING,
                ]))
                ->sum('amount');

            $freeAmount = (float) $earning->creator_amount - (float) $existingAllocated;
            if ($freeAmount <= 0) {
                continue;
            }
            $allocate = min($freeAmount, $amountNeeded - $allocated);
            CreatorWithdrawalAllocation::create([
                'withdrawal_request_id' => $request->id,
                'creator_earning_id' => $earning->id,
                'amount' => $allocate,
            ]);
            $allocated += $allocate;
        }

        if ($allocated < $amountNeeded) {
            throw new \RuntimeException('Insufficient available earnings to allocate.');
        }
    }

    public function processViaIoTec(CreatorWithdrawalRequest $request): void
    {
        $method = $request->payoutMethod;
        if (!$method || $method->method_type !== 'mobile_money' || !$method->phone_number) {
            $this->markFailed($request, 'Invalid mobile money payout method.');
            return;
        }

        $amountInt = (int) round((float) $request->amount);
        if ($amountInt < 500) {
            $this->markFailed($request, 'ioTec minimum disbursement is 500 UGX.');
            return;
        }

        $msisdn = IoTeCService::normalizePhone($method->phone_number);
        if (!IoTeCService::validatePhone($method->phone_number)) {
            $this->markFailed($request, 'Invalid Uganda phone number.');
            return;
        }

        $result = $this->iotecService->disburse(
            $request->reference,
            $request->amount,
            $msisdn,
            $method->account_name ?? $request->user->name,
            "Creator withdrawal #{$request->reference}"
        );

        CreatorPayoutAttempt::create([
            'withdrawal_request_id' => $request->id,
            'gateway' => 'iotec',
            'gateway_request' => [
                'payee' => IoTeCService::maskPhone($msisdn),
                'amount' => $amountInt,
                'externalId' => $request->reference,
            ],
            'gateway_response' => $result,
            'status' => isset($result['error']) ? 'failed' : 'sent',
            'external_id' => $result['request_id'] ?? null,
            'attempted_at' => now(),
        ]);

        if (isset($result['error'])) {
            $this->rollbackAllocation($request);
            $this->markFailed($request, $result['error']);
            return;
        }

        $request->update([
            'status' => CreatorWithdrawalRequest::STATUS_PAID,
            'processed_at' => now(),
            'gateway_used' => 'iotec',
            'gateway_reference' => $result['request_id'] ?? null,
        ]);
    }

    private function processViaIoTecBank(CreatorWithdrawalRequest $request): void
    {
        $method = $request->payoutMethod;
        if (!$method || $method->method_type !== 'bank' || !$method->account_number || !$method->account_name) {
            $this->markFailed($request, 'Invalid bank payout method.');
            return;
        }

        $amountInt = (int) round((float) $request->amount);
        if ($amountInt < 500) {
            $this->markFailed($request, 'ioTec minimum disbursement is 500 UGX.');
            return;
        }

        $result = $this->iotecService->bankDisburse(
            $request->reference,
            (float) $request->amount,
            $method->account_name,
            $method->account_number,
            $method->metadata['bank_id'] ?? null,
            $method->bank_code ?? ($method->metadata['bank_identification_code'] ?? null)
        );

        CreatorPayoutAttempt::create([
            'withdrawal_request_id' => $request->id,
            'gateway' => 'iotec',
            'gateway_request' => [
                'accountName' => $method->account_name,
                'accountNumber' => '****' . substr($method->account_number, -4),
                'amount' => $amountInt,
                'externalId' => $request->reference,
            ],
            'gateway_response' => $result,
            'status' => isset($result['error']) ? 'failed' : 'sent',
            'external_id' => $result['request_id'] ?? null,
            'attempted_at' => now(),
        ]);

        if (isset($result['error'])) {
            $this->rollbackAllocation($request);
            $this->markFailed($request, $result['error']);
            return;
        }

        $request->update([
            'status' => CreatorWithdrawalRequest::STATUS_PAID,
            'processed_at' => now(),
            'gateway_used' => 'iotec',
            'gateway_reference' => $result['request_id'] ?? null,
        ]);
    }

    private function processViaPawaPay(CreatorWithdrawalRequest $request): void
    {
        CreatorPayoutAttempt::create([
            'withdrawal_request_id' => $request->id,
            'gateway' => 'pawapay',
            'gateway_request' => [],
            'gateway_response' => ['error' => 'not_configured'],
            'status' => 'failed',
            'attempted_at' => now(),
            'notes' => 'PawaPay disbursement API not yet configured.',
        ]);
        $this->rollbackAllocation($request);
        $this->markFailed($request, 'PawaPay disbursement is not yet configured.');
    }

    private function markManualPending(CreatorWithdrawalRequest $request): void
    {
        CreatorPayoutAttempt::create([
            'withdrawal_request_id' => $request->id,
            'gateway' => 'manual',
            'gateway_request' => [],
            'gateway_response' => ['status' => 'manual_processing'],
            'status' => 'pending',
            'attempted_at' => now(),
            'notes' => 'Awaiting manual disbursement by admin.',
        ]);
    }

    private function rollbackAllocation(CreatorWithdrawalRequest $request): void
    {
        CreatorWithdrawalAllocation::where('withdrawal_request_id', $request->id)->delete();
    }

    public function markFailed(CreatorWithdrawalRequest $request, string $reason): void
    {
        $this->rollbackAllocation($request);
        $request->update([
            'status' => CreatorWithdrawalRequest::STATUS_FAILED,
            'failure_reason' => $reason,
            'processed_at' => now(),
        ]);
    }

    public function reject(CreatorWithdrawalRequest $request, string $reason): void
    {
        if (!in_array($request->status, [
            CreatorWithdrawalRequest::STATUS_PENDING,
            CreatorWithdrawalRequest::STATUS_UNDER_REVIEW,
            CreatorWithdrawalRequest::STATUS_APPROVED,
        ])) {
            return;
        }
        $this->rollbackAllocation($request);
        $request->update([
            'status' => CreatorWithdrawalRequest::STATUS_REJECTED,
            'failure_reason' => $reason,
            'admin_notes' => $reason,
        ]);
    }

    public function cancel(CreatorWithdrawalRequest $request): void
    {
        if (!$request->isCancellable()) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'withdrawal' => ['This withdrawal cannot be cancelled.'],
            ]);
        }

        $this->rollbackAllocation($request);
        $request->update(['status' => CreatorWithdrawalRequest::STATUS_CANCELLED]);
    }
}
