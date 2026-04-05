<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Schedule::command('reminders:whatsapp-links')->dailyAt('18:00');
Schedule::command('pagamenti:genera-mensili')->monthlyOn(now()->endOfMonth()->day, '23:30');

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
