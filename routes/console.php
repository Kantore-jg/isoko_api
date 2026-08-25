<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Purger chaque nuit les tokens API expirés ou révoqués (Sanctum).
Schedule::command('sanctum:prune-expired --hours=24')->dailyAt('00:00');
