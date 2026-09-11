<?php

use App\Http\Controllers\CronController;
use App\Http\Controllers\Ia\MaterialController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Cron por URL para hospedagem compartilhada (ver CronController).
Route::get('/cron/{token}', CronController::class)
    ->where('token', '[A-Za-z0-9_-]{16,128}')
    ->name('cron');

// Arquivo de um material da agente (imagem dos programas etc.), buscado pelo Instagram.
Route::get('/materiais/{token}/{nome}', MaterialController::class)
    ->where(['token' => '[A-Za-z0-9]{40}', 'nome' => '[A-Za-z0-9._-]+'])
    ->name('ia.material');
