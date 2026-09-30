<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// ─── Scheduled tasks (run by `php artisan schedule:work` / cron `schedule:run`) ───
\Illuminate\Support\Facades\Schedule::command('appointments:send-reminders')
    ->hourly()
    ->withoutOverlapping();
