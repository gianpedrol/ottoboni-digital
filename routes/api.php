<?php

use App\Http\Controllers\FollowupCallbackController;
use Illuminate\Support\Facades\Route;

// Callback do FOLLOWUP EXECUTOR (n8n). A autenticação é o HMAC do corpo
// (X-Signature) validado no controller — sem assinatura válida, 401.
Route::post('/followup/callback', FollowupCallbackController::class)
    ->name('followup.callback');
