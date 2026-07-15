<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PaymentGateway;
use App\Models\PaymentTransaction;
use App\Models\Payment;
use App\Models\Movie;
use App\Models\TVShow;
use App\Models\SubscriptionPlan;
use App\Services\PaymentApprovalService;
use App\Services\PendingPaymentResolverService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * @group Payments
 *
 * Payment gateways (list), initiate payment (rent/buy/subscription), upload proof (manual), verify.
 */
class PaymentController extends Controller
{
    public function __construct(
        private readonly PendingPaymentResolverService $pendingPaymentResolver,
    ) {
    }

    /**
     * Get all active payment gateways
     *
     * Public list of configured payment gateways. Use this to render the
     * “Choose payment method” UI.
     *
     * Gateways can be:
     * - `type = AUTOMATIC` (eg. ioTec Pay, PawaPay)
     * - `type = MANUAL` (bank transfer / manual mobile money with proof upload)
     *
     * @response 200 [{
     *  "id": 7,
     *  "name": "ioTec Pay",
     *  "slug": "iotec",
     *  "code": "iotec",
     *  "displayName": "Mobile Money - Iotec",
     *  "display_name": "Mobile Money - Iotec",
     *  "logoPath": "payment-gateways/01KJZWAK4P244CXYXF0NQE7QDX.jpeg",
     *  "logoUrl": "https://portal.naraboxtv.com/storage/payment-gateways/01KJZWAK4P244CXYXF0NQE7QDX.jpeg",
     *  "helperText": null,
     *  "type": "AUTOMATIC",
     *  "instructions": null,
     *  "paymentDetails": null
     * }]
     */
    public function gateways()
    {
        $gateways = PaymentGateway::where('is_active', true)
            ->orderBy('sort_order')
            ->get()
            ->map(function ($gateway) {
                $logoUrl = null;
                if (! empty($gateway->logo_path)) {
                    $path = $gateway->logo_path;
                    if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
                        $logoUrl = $path;
                    } else {
                        $logoUrl = url(Storage::url($path));
                    }
                }

                return [
                    'id' => $gateway->id,
                    'name' => $gateway->name,
                    'slug' => $gateway->slug,
                    'code' => $gateway->code,
                    'displayName' => $gateway->display_name,
                    'display_name' => $gateway->display_name,
                    'description' => $gateway->description,
                    'logoPath' => $gateway->logo_path,
                    'logoUrl' => $logoUrl,
                    'logo' => $logoUrl,
                    'helperText' => $gateway->helper_text,
                    'helper_text' => $gateway->helper_text,
                    'type' => $gateway->type,
                    'instructions' => $gateway->instructions,
                    'paymentDetails' => $gateway->payment_details,
                    'is_active' => (bool) $gateway->is_active,
                ];
            });

        return response()->json($gateways);
    }

    /**
     * Initiate a payment (rent, buy, or subscription)
     *
     * Creates a `payment_transactions` row and returns a `transaction_ref`
     * that the frontend can use with the appropriate gateway flow.
     *
     * For **manual** gateways:
     * - Returns bank/mobile-money instructions and `paymentDetails`
     * - Frontend must call `POST /api/v1/payments/upload-proof` afterwards
     *
     * For **automatic** gateways:
     * - Returns a generic “PENDING” status and `transaction_ref`
     * - The actual collection happens via gateway-specific endpoints
     *   (eg. ioTec, PawaPay) or external UIs.
     *
     * @authenticated
     *
     * @bodyParam type string required One of `RENT`, `BUY`, `SUBSCRIPTION`. Example: RENT
     * @bodyParam media_id integer required_if:type,RENT,BUY The movie/TV show id for rent/buy. Example: 1
     * @bodyParam media_type string required_if:type,RENT,BUY Must be `MOVIE` or `TV_SHOW`. Example: MOVIE
     * @bodyParam gateway_id integer required The `id` of the selected payment gateway. Example: 3
     * @bodyParam subscription_plan_id integer required_if:type,SUBSCRIPTION The `id` of the subscription plan. Example: 2
     * @bodyParam phone string nullable Phone number (used by some mobile money gateways). Example: 256780000000
     *
     * @response 200 scenario="Manual gateway" {
     *  "transaction_ref": "NBX-3Y7Z5K0PQ8LM",
     *  "amount": 1000,
     *  "status": "PENDING",
     *  "gateway_type": "MANUAL",
     *  "instructions": "Send UGX 1,000 to MTN 0770 000 000 and include your transaction_ref in the note.",
     *  "payment_details": {
     *    "account_name": "NaraBox TV",
     *    "account_number": "0770000000",
     *    "provider": "MTN MoMo"
     *  },
     *  "message": "Please follow the instructions and upload proof of payment"
     * }
     *
     * @response 200 scenario="Automatic gateway" {
     *  "transaction_ref": "NBX-5K8LM3Y7Z0PQ",
     *  "amount": 8500,
     *  "status": "PENDING",
     *  "gateway_type": "AUTOMATIC",
     *  "message": "Payment initiated. Please complete on your device."
     * }
     *
     * @response 400 {
     *  "error": "Invalid amount"
     * }
     *
     * @response 401 {
     *  "error": "Unauthorized"
     * }
     */
    public function initiate(Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $request->validate([
            'media_id' => 'required_if:type,RENT,BUY|integer',
            'media_type' => 'required_if:type,RENT,BUY|in:MOVIE,TV_SHOW',
            'type' => 'required|in:RENT,BUY,SUBSCRIPTION',
            'gateway_id' => 'required|exists:payment_gateways,id',
            'subscription_plan_id' => 'required_if:type,SUBSCRIPTION|exists:subscription_plans,id',
            'phone' => 'nullable|string', // For mobile money gateways
        ]);

        $gateway = PaymentGateway::findOrFail($request->gateway_id);
        $amount = 0;
        $transactionable = null;
        $subscriptionPlan = null;

        // Calculate amount and get transactionable
        if ($request->type === 'RENT' || $request->type === 'BUY') {
            $media = $request->media_type === 'MOVIE'
                ? Movie::findOrFail($request->media_id)
                : TVShow::findOrFail($request->media_id);

            $transactionable = $media;
            $amount = $request->type === 'RENT' 
                ? ($media->price_rent ?? 0)
                : ($media->price_buy ?? 0);
        } elseif ($request->type === 'SUBSCRIPTION') {
            $subscriptionPlan = SubscriptionPlan::findOrFail($request->subscription_plan_id);
            $amount = $subscriptionPlan->price;
        }

        if ($amount <= 0) {
            return response()->json(['error' => 'Invalid amount'], 400);
        }

        // Create transaction
        $transaction = PaymentTransaction::create([
            'user_id' => $user->id,
            'payment_gateway_id' => $gateway->id,
            'gateway_code' => $gateway->code,
            'type' => $request->type,
            'transactionable_type' => $transactionable ? get_class($transactionable) : null,
            'transactionable_id' => $transactionable ? $transactionable->id : null,
            'subscription_plan_id' => $subscriptionPlan ? $subscriptionPlan->id : null,
            'transaction_ref' => 'NBX-' . strtoupper(Str::random(12)),
            'amount' => $amount,
            'status' => 'PENDING',
        ]);

        // If manual gateway, return instructions
        if ($gateway->type === 'MANUAL') {
            return response()->json([
                'transaction_ref' => $transaction->transaction_ref,
                'amount' => $amount,
                'status' => 'PENDING',
                'gateway_type' => 'MANUAL',
                'instructions' => $gateway->instructions,
                'payment_details' => $gateway->payment_details,
                'message' => 'Please follow the instructions and upload proof of payment',
            ]);
        }

        // For automatic gateways, process payment
        // This would integrate with actual payment gateway APIs
        return response()->json([
            'transaction_ref' => $transaction->transaction_ref,
            'amount' => $amount,
            'status' => 'PENDING',
            'gateway_type' => 'AUTOMATIC',
            'message' => 'Payment initiated. Please complete on your device.',
        ]);
    }

    /**
     * Upload payment proof for manual payments
     *
     * After a manual payment (bank or mobile money), the user uploads a screenshot
     * or PDF. Admins then review and approve/reject in the back office.
     *
     * This endpoint:
     * - Stores the file under `storage/app/public/payment-proofs`
     * - Creates a `payments` row with status `PENDING`
     *
     * @authenticated
     *
     * @bodyParam transaction_ref string required The `transaction_ref` returned from `/payments/initiate`. Example: NBX-3Y7Z5K0PQ8LM
     * @bodyParam proof file required Image or PDF file up to 10MB. Must be jpeg, jpg, png, or pdf.
     * @bodyParam notes string nullable Optional notes from the user. Max 1000 characters. Example: Paid using MTN at 10:32pm.
     *
     * @response 200 {
     *  "success": true,
     *  "message": "Payment proof uploaded. Waiting for admin approval.",
     *  "payment_id": 42
     * }
     *
     * @response 400 {
     *  "error": "Transaction is not pending"
     * }
     *
     * @response 401 {
     *  "error": "Unauthorized"
     * }
     */
    public function uploadProof(Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $request->validate([
            'transaction_ref' => 'required|exists:payment_transactions,transaction_ref',
            'proof' => 'required|file|mimes:jpeg,jpg,png,pdf|max:10240', // 10MB max
            'notes' => 'nullable|string|max:1000',
        ]);

        $transaction = PaymentTransaction::where('transaction_ref', $request->transaction_ref)
            ->where('user_id', $user->id)
            ->firstOrFail();

        if ($transaction->status !== 'PENDING') {
            return response()->json(['error' => 'Transaction is not pending'], 400);
        }

        // Store proof file
        $proofPath = $request->file('proof')->store('payment-proofs', 'public');

        // Create payment record
        $payment = Payment::create([
            'user_id' => $user->id,
            'transaction_id' => $transaction->id,
            'payment_gateway_id' => $transaction->payment_gateway_id,
            'proof_path' => $proofPath,
            'notes' => $request->notes,
            'status' => 'PENDING',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Payment proof uploaded. Waiting for admin approval.',
            'payment_id' => $payment->id,
        ]);
    }

    /**
     * Verify payment (for automatic gateways)
     *
     * Frontend helper to confirm if a payment has been completed and access granted.
     *
     * Behaviour:
     * - For **manual** gateways: checks the related `payments` record and returns:
     *   - `APPROVED` when admin has approved (and access has been granted)
     *   - `REJECTED` when admin rejected
     *   - `PENDING` while awaiting review
     * - For **automatic** gateways: currently simulates a success and calls
     *   `PaymentApprovalService::grantAccess()`. In production, this should
     *   be wired to the real provider status API.
     *
     * @authenticated
     *
     * @bodyParam transaction_ref string required The `transaction_ref` to verify. Example: NBX-3Y7Z5K0PQ8LM
     *
     * @response 200 scenario="Manual gateway approved" {
     *  "success": true,
     *  "status": "APPROVED",
     *  "message": "Payment approved. Access granted."
     * }
     *
     * @response 200 scenario="Manual gateway pending" {
     *  "success": false,
     *  "status": "PENDING",
     *  "message": "Payment is pending admin approval"
     * }
     *
     * @response 200 scenario="Automatic gateway success" {
     *  "success": true,
     *  "transaction": {
     *    "ref": "NBX-5K8LM3Y7Z0PQ",
     *    "status": "SUCCESS",
     *    "type": "RENT"
     *  }
     * }
     *
     * @response 401 {
     *  "error": "Unauthorized"
     * }
     */
    public function verify(Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $request->validate([
            'transaction_ref' => 'required|exists:payment_transactions,transaction_ref',
        ]);

        $transaction = PaymentTransaction::where('transaction_ref', $request->transaction_ref)
            ->where('user_id', $user->id)
            ->firstOrFail();

        // For manual payments, check if approved
        if ($transaction->paymentGateway->type === 'MANUAL') {
            $payment = Payment::where('transaction_id', $transaction->id)->first();
            
            if (!$payment) {
                return response()->json([
                    'success' => false,
                    'message' => 'Payment proof not uploaded yet',
                ]);
            }

            if ($payment->status === 'APPROVED') {
                PaymentApprovalService::grantAccess($transaction);
                return response()->json([
                    'success' => true,
                    'status' => 'APPROVED',
                    'message' => 'Payment approved. Access granted.',
                ]);
            } elseif ($payment->status === 'REJECTED') {
                return response()->json([
                    'success' => false,
                    'status' => 'REJECTED',
                    'message' => $payment->admin_notes ?? 'Payment was rejected',
                ]);
            }

            return response()->json([
                'success' => false,
                'status' => 'PENDING',
                'message' => 'Payment is pending admin approval',
            ]);
        }

        if ($transaction->status === 'SUCCESS') {
            return response()->json([
                'success' => true,
                'transaction' => [
                    'ref' => $transaction->transaction_ref,
                    'status' => $transaction->status,
                    'type' => $transaction->type,
                ],
            ]);
        }

        if ($transaction->status === 'FAILED') {
            return response()->json([
                'success' => false,
                'status' => 'FAILED',
                'message' => $transaction->failure_reason ?? 'Payment failed.',
            ], 400);
        }

        $transaction = $this->pendingPaymentResolver->resolve($transaction);

        if ($transaction->status === 'FAILED') {
            return response()->json([
                'success' => false,
                'status' => 'FAILED',
                'message' => $transaction->failure_reason ?? 'Payment failed.',
            ], 400);
        }

        if ($transaction->status !== 'SUCCESS') {
            return response()->json([
                'success' => false,
                'status' => 'PENDING',
                'message' => 'Payment is still being confirmed. Please give it a moment.',
            ], 202);
        }

        return response()->json([
            'success' => true,
            'transaction' => [
                'ref' => $transaction->transaction_ref,
                'status' => $transaction->status,
                'type' => $transaction->type,
            ],
        ]);
    }
}
