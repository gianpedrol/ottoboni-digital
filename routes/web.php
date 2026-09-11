<?php

use App\Http\Controllers\CronController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Cron por URL para hospedagem compartilhada (ver CronController).
Route::get('/cron/{token}', CronController::class)
    ->where('token', '[A-Za-z0-9_-]{16,128}')
    ->name('cron');
