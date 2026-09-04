<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Purger chaque nuit les tokens API expirés ou révoqués (Sanctum).
Schedule::command('sanctum:prune-expired --hours=24')->dailyAt('00:00');

// Chaque jour à 00:05, marquer les obligations dont l'échéance est dépassée comme OVERDUE.
Schedule::command('rent:mark-overdue')->dailyAt('00:05');
