<?php

use App\Http\Controllers\FollowupCallbackController;
use App\Http\Controllers\Ia\ContextoController;
use App\Http\Controllers\Ia\DmEsperaController;
use App\Http\Controllers\Ia\EnvioCallbackController;
use App\Http\Controllers\Ia\RascunhoController;
use App\Http\Middleware\VerificaAssinaturaIa;
use Illuminate\Support\Facades\Route;

// Callback do FOLLOWUP EXECUTOR (n8n). A autenticação é o HMAC do corpo
// (X-Signature) validado no controller — sem assinatura válida, 401.
Route::post('/followup/callback', FollowupCallbackController::class)
    ->name('followup.callback');

/*
|--------------------------------------------------------------------------
| Área de treinamento das agentes de IA
|--------------------------------------------------------------------------
| Tudo assinado com HMAC-SHA256 do corpo cru (X-Signature), igual ao
| contrato do follow-up. Detalhes em docs/n8n-treinamento-ia.md.
*/

Route::middleware(VerificaAssinaturaIa::class)->prefix('ia')->name('ia.')->group(function (): void {
    // O n8n busca o prompt publicado, as regras, os exemplos e os cards.
    Route::post('/contexto', ContextoController::class)->name('contexto');

    // O n8n manda o rascunho e recebe a ordem: envia ou vai para a fila.
    Route::post('/rascunho', RascunhoController::class)->name('rascunho');

    // O n8n confirma o resultado do envio aprovado.
    Route::post('/envio/callback', EnvioCallbackController::class)->name('envio.callback');

    // O n8n confirma que o DM de espera saiu.
    Route::post('/dm-espera', DmEsperaController::class)->name('dm-espera');
});
