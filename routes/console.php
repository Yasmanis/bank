<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

//// Liquidar el sorteo AM a las 3:00 PM
//Schedule::command('app:process-settlements', [now()->format('Y-m-d'), 'am'])
//    ->dailyAt('15:00');
//
//// Liquidar el sorteo PM a las 10:00 PM
//Schedule::command('app:process-settlements', [now()->format('Y-m-d'), 'pm'])
//    ->dailyAt('22:00');
