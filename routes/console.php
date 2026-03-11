<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Schedule subscription expiration check to run every hour
Schedule::command('subscriptions:expire')->hourly();

// Reconcile pending ioTec Pay transactions every 10 minutes
Schedule::command('iotec:reconcile-pending')->everyTenMinutes();

// Reconcile pending PawaPay deposits every 5 minutes
Schedule::command('pawapay:reconcile-pending')->everyFiveMinutes();
