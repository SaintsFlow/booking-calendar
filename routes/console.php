<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Синхронизация товаров из Битрикс24 каждую полночь
Schedule::command('bitrix24:sync-products')->dailyAt('00:00');

// Синхронизация пользователей из Битрикс24 каждый день в 01:00
Schedule::command('bitrix24:sync-users')->dailyAt('01:00');
