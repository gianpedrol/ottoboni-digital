<?php

namespace App\Http\Controllers;

use App\Support\SegredosDoPainel;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Artisan;

/**
 * Hospedagem compartilhada (KingHost) não deixa agendar um comando de shell:
 * o cron dela chama uma URL. Esta rota é o "php artisan schedule:run" por
 * HTTP, protegida por um token longo (CRON_TOKEN no .env ou, sem ele,
 * derivado da APP_KEY — a tela Configuração da IA mostra a URL pronta).
 *
 * Quando a fila é de banco, também esvazia a fila na mesma chamada, para não
 * depender de um worker que a hospedagem não tem.
 *
 *   GET /cron/{token}   → agende no painel da hospedagem a cada minuto
 */
class CronController extends Controller
{
    public function __invoke(string $token): Response
    {
        $esperado = SegredosDoPainel::cronToken();

        if ($esperado === '' || ! hash_equals($esperado, $token)) {
            abort(404);
        }

        $saida = [];

        Artisan::call('schedule:run');
        $saida[] = trim(Artisan::output());

        if (config('queue.default') !== 'sync') {
            Artisan::call('queue:work', ['--stop-when-empty' => true, '--max-time' => 50, '--tries' => 3]);
            $saida[] = trim(Artisan::output());
        }

        $texto = trim(implode("\n", array_filter($saida)));

        return response($texto !== '' ? $texto : 'ok', 200, ['Content-Type' => 'text/plain; charset=utf-8']);
    }
}
