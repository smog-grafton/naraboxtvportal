<?php

namespace App\Filament\Pages;

use App\Models\PaymentAttempt;
use App\Models\SecurityEvent;
use App\Models\User;
use Filament\Pages\Page;

class SecurityDashboard extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-shield-check';
    protected static ?string $navigationGroup = 'Security';
    protected static ?int $navigationSort = 2;
    protected static string $view = 'filament.pages.security-dashboard';

    public function metrics(): array
    {
        return [
            'Attempts (10 min)' => PaymentAttempt::where('occurred_at', '>=', now()->subMinutes(10))->count(),
            'Attempts (hour)' => PaymentAttempt::where('occurred_at', '>=', now()->subHour())->count(),
            'Attempts (today)' => PaymentAttempt::whereDate('occurred_at', today())->count(),
            'Blocked (today)' => PaymentAttempt::whereDate('occurred_at', today())->where('allowed', false)->count(),
            'Enumeration detections' => SecurityEvent::where('event_type', 'PHONE_ENUMERATION_DETECTED')->whereDate('occurred_at', today())->count(),
            'Protected payer attempts' => SecurityEvent::where('event_type', 'PROTECTED_PAYER_ATTEMPT')->whereDate('occurred_at', today())->count(),
            'Payment restricted' => User::where('account_status', 'PAYMENT_RESTRICTED')->count(),
            'Suspended / banned' => User::whereIn('account_status', ['SUSPENDED', 'BANNED'])->count(),
        ];
    }

    public function criticalEvents()
    {
        return SecurityEvent::with('user:id,name,email')->whereIn('risk_level', ['HIGH', 'CRITICAL'])->latest('occurred_at')->limit(20)->get();
    }
}
