<?php

namespace App\Events;

use App\Models\PaymentTransaction;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PaymentSucceeded
{
    use Dispatchable, SerializesModels;

    public function __construct(public PaymentTransaction $transaction)
    {
    }
}
