<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('app:cleanup-lists')->dailyAt('03:00');

Schedule::command('app:scrape-results am')
    ->everyFifteenMinutes()
    ->between('13:30', '15:00')
    ->withoutOverlapping()
    ->onFailure(function () {
        \Illuminate\Support\Facades\Log::critical("ALERTA: El bot de scraping ha fallado repetidamente para el sorteo AM.");
    });

Schedule::command('app:scrape-results am')
    ->everyFifteenMinutes()
    ->between('21:30', '23:00')
    ->withoutOverlapping()
    ->onFailure(function () {
        \Illuminate\Support\Facades\Log::critical("ALERTA: El bot de scraping ha fallado repetidamente para el sorteo PM.");
    });
