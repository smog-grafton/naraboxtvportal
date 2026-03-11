<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\PaymentGateway;
use Illuminate\Support\Str;

class ManualPaymentGatewaySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $gateways = [
            [
                'name' => 'MTN Mobile Money',
                'slug' => 'mtn',
                'type' => 'MANUAL',
                'display_name' => 'MTN Mobile Money',
                'description' => 'Send money via MTN Mobile Money',
                'instructions' => '<p><strong>How to Pay via MTN Mobile Money:</strong></p>
<ol>
<li>Go to your MTN Mobile Money menu on your phone</li>
<li>Select "Send Money"</li>
<li>Enter the phone number: <strong>078377122</strong></li>
<li>Enter the amount shown in your transaction</li>
<li>Enter your PIN to confirm</li>
<li>Take a screenshot of the confirmation message</li>
<li>Upload the screenshot as proof of payment</li>
</ol>
<p><strong>Important:</strong> Make sure to include the transaction reference in your payment notes.</p>',
                'payment_details' => [
                    'phone_numbers' => [
                        [
                            'network' => 'MTN',
                            'number' => '078377122'
                        ]
                    ]
                ],
                'is_active' => true,
                'sort_order' => 1,
            ],
            [
                'name' => 'Airtel Money',
                'slug' => 'airtel',
                'type' => 'MANUAL',
                'display_name' => 'Airtel Money',
                'description' => 'Send money via Airtel Money',
                'instructions' => '<p><strong>How to Pay via Airtel Money:</strong></p>
<ol>
<li>Go to your Airtel Money menu on your phone</li>
<li>Select "Send Money"</li>
<li>Enter the phone number: <strong>070293354</strong></li>
<li>Enter the amount shown in your transaction</li>
<li>Enter your PIN to confirm</li>
<li>Take a screenshot of the confirmation message</li>
<li>Upload the screenshot as proof of payment</li>
</ol>
<p><strong>Important:</strong> Make sure to include the transaction reference in your payment notes.</p>',
                'payment_details' => [
                    'phone_numbers' => [
                        [
                            'network' => 'AIRTEL',
                            'number' => '070293354'
                        ]
                    ]
                ],
                'is_active' => true,
                'sort_order' => 2,
            ],
            [
                'name' => 'Bank Transfer',
                'slug' => 'bank-transfer',
                'type' => 'MANUAL',
                'display_name' => 'Bank Transfer',
                'description' => 'Transfer money directly to our bank account',
                'instructions' => '<p><strong>How to Pay via Bank Transfer:</strong></p>
<ol>
<li>Go to your bank (mobile banking, online banking, or bank branch)</li>
<li>Initiate a transfer to the account details below</li>
<li>Enter the exact amount shown in your transaction</li>
<li>Use your transaction reference as the payment reference/narrative</li>
<li>Complete the transfer</li>
<li>Take a screenshot or download the receipt</li>
<li>Upload the receipt as proof of payment</li>
</ol>
<p><strong>Bank Account Details:</strong></p>
<ul>
<li><strong>Bank Name:</strong> XYZ Bank</li>
<li><strong>Branch:</strong> Kampala Branch</li>
<li><strong>Account Number:</strong> 123456789</li>
<li><strong>Account Name:</strong> NaraBox Ltd</li>
</ul>
<p><strong>Important:</strong> Always include your transaction reference in the payment narrative/reference field.</p>',
                'payment_details' => [
                    'bank_account' => [
                        'bank_name' => 'XYZ Bank',
                        'branch' => 'Kampala Branch',
                        'account_number' => '123456789',
                        'account_name' => 'NaraBox Ltd'
                    ]
                ],
                'is_active' => true,
                'sort_order' => 3,
            ],
        ];

        foreach ($gateways as $gateway) {
            PaymentGateway::updateOrCreate(
                ['slug' => $gateway['slug']],
                $gateway
            );
        }

        $this->command->info('Manual payment gateways seeded successfully!');
        $this->command->info('Created: MTN Mobile Money, Airtel Money, and Bank Transfer gateways.');
    }
}
