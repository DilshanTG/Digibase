<?php

use Illuminate\Support\Facades\Schedule;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// 📉 Auto-Pruning Scheduler
Schedule::command('api:prune-analytics')->daily();

// 🗄️ Backup Schedules
Schedule::command('backup:clean')->daily();
Schedule::command('backup:run')->daily();
