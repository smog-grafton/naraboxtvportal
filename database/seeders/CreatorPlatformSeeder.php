<?php

namespace Database\Seeders;

use App\Models\CreatorApplication;
use App\Models\CreatorPayoutOption;
use App\Models\CreatorPermission;
use App\Models\EmailTemplate;
use App\Models\FinancialSetting;
use Illuminate\Database\Seeder;

class CreatorPlatformSeeder extends Seeder
{
    public function run(): void
    {
        FinancialSetting::firstOrCreate([], [
            'currency' => 'UGX',
            'commission_rate' => 30,
            'creator_share_bps' => 7000,
            'platform_share_bps' => 3000,
            'subscription_pool_bps' => 4000,
            'settlement_frequency' => 'monthly',
            'creator_hold_days' => 7,
            'min_withdrawal_amount' => 10000,
            'min_withdrawal_minor' => 10000,
            'withdrawal_fee_minor' => 0,
            'auto_payout_enabled' => false,
            'manual_payout_enabled' => true,
            'manual_approval_required' => true,
            'iotec_disbursement_enabled' => false,
            'pawapay_disbursement_enabled' => false,
            'unverified_creator_earns' => false,
            'earnings_pending_until_approval' => true,
            'unverified_earnings_policy' => 'platform_retains',
            'allowed_payout_methods' => ['mobile_money', 'bank'],
            'qualified_watch_seconds' => 30,
            'max_qualified_plays_per_day' => 10,
            'refund_chargeback_policy' => 'Refunds and chargebacks are deducted before creator earnings are finalized.',
        ]);

        foreach ($this->payoutOptions() as $option) {
            $record = CreatorPayoutOption::firstOrCreate(['key' => $option['key']], $option);
            if ($record->key === 'iotec') {
                $record->refreshConfigurationStatus();
            }
        }

        CreatorApplication::query()
            ->with('permission')
            ->orderBy('id')
            ->chunkById(100, function ($applications): void {
                foreach ($applications as $application) {
                    $preset = $application->status === CreatorApplication::STATUS_APPROVED
                        ? 'standard'
                        : 'applicant';
                    $permission = CreatorPermission::firstOrCreate(
                        ['user_id' => $application->user_id],
                        [
                            'creator_application_id' => $application->id,
                            'permission_preset' => $preset,
                            'capabilities' => config("creator.permission_presets.{$preset}", []),
                            'identity_verified' => $application->status === CreatorApplication::STATUS_APPROVED,
                            'draft_submission_enabled' => true,
                        ]
                    );
                    if (! is_array($permission->capabilities)) {
                        $permission->forceFill([
                            'permission_preset' => $preset,
                            'capabilities' => config("creator.permission_presets.{$preset}", []),
                        ])->save();
                    }
                }
            });

        foreach ($this->emailTemplates() as $template) {
            EmailTemplate::firstOrCreate(['name' => $template['name']], $template);
        }

        $this->command?->info('Creator platform defaults, payout options, permissions and lifecycle templates are ready.');
    }

    private function payoutOptions(): array
    {
        return [
            [
                'key' => 'iotec',
                'label' => 'ioTec Pay',
                'category' => 'mobile_money_and_bank',
                'automation_type' => 'automatic',
                'adapter' => 'iotec',
                'supported_countries' => ['UG'],
                'supported_currencies' => ['UGX'],
                'minimum_amount_minor' => 500,
                'fee_minor' => 0,
                'is_enabled' => false,
                'is_configured' => false,
                'configuration_status' => 'incomplete',
                'configuration_notes' => 'Add client ID, client secret and wallet ID in Payment Gateways before enabling automated payouts.',
                'sort_order' => 10,
            ],
            [
                'key' => 'mtn_mobile_money_manual',
                'label' => 'MTN Mobile Money',
                'category' => 'mobile_money',
                'automation_type' => 'manual',
                'supported_countries' => ['UG'],
                'supported_currencies' => ['UGX'],
                'is_enabled' => true,
                'is_configured' => true,
                'configuration_status' => 'not_required',
                'sort_order' => 20,
            ],
            [
                'key' => 'airtel_money_manual',
                'label' => 'Airtel Money',
                'category' => 'mobile_money',
                'automation_type' => 'manual',
                'supported_countries' => ['UG'],
                'supported_currencies' => ['UGX'],
                'is_enabled' => true,
                'is_configured' => true,
                'configuration_status' => 'not_required',
                'sort_order' => 30,
            ],
            [
                'key' => 'bank_transfer_manual',
                'label' => 'Bank transfer',
                'category' => 'bank',
                'automation_type' => 'manual',
                'supported_countries' => ['UG'],
                'supported_currencies' => ['UGX'],
                'is_enabled' => true,
                'is_configured' => true,
                'configuration_status' => 'not_required',
                'sort_order' => 40,
            ],
            [
                'key' => 'other_manual',
                'label' => 'Other manual payout',
                'category' => 'other',
                'automation_type' => 'manual',
                'supported_countries' => ['UG'],
                'supported_currencies' => ['UGX'],
                'is_enabled' => false,
                'is_configured' => true,
                'configuration_status' => 'not_required',
                'sort_order' => 50,
            ],
        ];
    }

    private function emailTemplates(): array
    {
        $definitions = [
            'creator_application_draft_saved' => ['Creator application saved', 'Your creator application draft is safe and ready when you return.'],
            'creator_application_submitted' => ['Creator application received', 'Your application is in the NaraBox TV review queue.'],
            'creator_application_under_review' => ['Your creator application is under review', 'The creator team has started reviewing your application.'],
            'creator_application_information_requested' => ['More information is needed', '{{message}}'],
            'creator_application_information_submitted' => ['Creator information submitted', '{{message}}'],
            'creator_application_approved' => ['Welcome to the NaraBox TV creator programme', 'Your creator identity has been approved.'],
            'creator_application_rejected' => ['Creator application update', '{{message}}'],
            'creator_application_suspended' => ['Creator account suspended', '{{message}}'],
            'creator_claim_submitted' => ['Creator profile claim received', 'Your claim for {{creator_name}} is ready for review.'],
            'creator_claim_under_review' => ['Creator profile claim under review', 'We have started reviewing your claim for {{creator_name}}.'],
            'creator_claim_information_requested' => ['More claim information is needed', '{{message}}'],
            'creator_claim_approved' => ['Creator profile claim approved', 'Your claim for {{creator_name}} has been approved.'],
            'creator_claim_rejected' => ['Creator profile claim update', '{{message}}'],
            'creator_claim_transferred' => ['Creator profile ownership updated', 'Ownership for {{creator_name}} has been securely linked to your account.'],
            'creator_welcome' => ['Your NaraBox TV creator workspace is ready', 'Upload your first title, complete your public profile, and follow every review from one place.'],
            'creator_content_uploaded' => ['Upload received: {{content_title}}', 'Your media upload has been received and is entering processing.'],
            'creator_content_processing' => ['Processing started: {{content_title}}', 'NBX is preparing your media for playback.'],
            'creator_content_ready' => ['Media ready: {{content_title}}', 'Your processed media is ready for the next publishing step.'],
            'creator_content_submitted' => ['Submitted for review: {{content_title}}', 'Your title has been sent to the editorial review team.'],
            'creator_content_changes_requested' => ['Changes requested: {{content_title}}', '{{message}}'],
            'creator_content_approved' => ['Approved: {{content_title}}', 'Your title has passed editorial review.'],
            'creator_content_rejected' => ['Content review update: {{content_title}}', '{{message}}'],
            'creator_content_published' => ['Now published: {{content_title}}', 'Your title is live on NaraBox TV.'],
            'creator_content_unpublished' => ['Publishing update: {{content_title}}', '{{message}}'],
            'creator_earnings_available' => ['Creator earnings are available', '{{message}}'],
            'creator_settlement_finalized' => ['Creator settlement finalized', '{{message}}'],
            'creator_withdrawal_requested' => ['Withdrawal request received', '{{message}}'],
            'creator_withdrawal_approved' => ['Withdrawal approved', '{{message}}'],
            'creator_withdrawal_processing' => ['Withdrawal is processing', '{{message}}'],
            'creator_withdrawal_paid' => ['Withdrawal paid', '{{message}}'],
            'creator_withdrawal_failed' => ['Withdrawal needs attention', '{{message}}'],
            'creator_withdrawal_rejected' => ['Withdrawal request update', '{{message}}'],
        ];

        return collect($definitions)->map(function (array $definition, string $name): array {
            [$subject, $message] = $definition;

            return [
                'name' => $name,
                'subject' => $subject,
                'preheader' => strip_tags($message),
                'preview_text' => strip_tags($message),
                'template_type' => 'transactional',
                'body' => $this->emailFrame($subject, $message),
                'variables' => [
                    'user_name' => 'Creator display name',
                    'message' => 'Creator-visible status message',
                    'creator_name' => 'VJ or library name',
                    'content_title' => 'Movie or TV show title',
                    'action_url' => 'Creator workspace URL',
                ],
                'is_active' => true,
            ];
        })->values()->all();
    }

    private function emailFrame(string $heading, string $message): string
    {
        return '<!doctype html><html><body style="margin:0;background:#020504;color:#f8fafc;font-family:Arial,sans-serif">'
            .'<div style="max-width:640px;margin:0 auto;padding:32px 18px"><div style="border:1px solid #164e3f;background:#07110d">'
            .'<div style="padding:18px 24px;border-bottom:1px solid #164e3f;color:#34d399;font-weight:800;letter-spacing:.15em">NARABOX TV · CREATOR</div>'
            .'<div style="padding:30px 24px"><h1 style="margin:0 0 18px;font-size:28px;color:#fff">'.e($heading).'</h1>'
            .'<p style="font-size:16px;line-height:1.7;color:#d1d5db">Hello {{user_name}},</p>'
            .'<p style="font-size:16px;line-height:1.7;color:#d1d5db">'.$message.'</p>'
            .'<p style="margin-top:28px"><a href="{{action_url}}" style="display:inline-block;background:#34d399;color:#02100b;padding:13px 20px;text-decoration:none;font-weight:800">OPEN CREATOR WORKSPACE</a></p>'
            .'</div></div></div></body></html>';
    }
}
