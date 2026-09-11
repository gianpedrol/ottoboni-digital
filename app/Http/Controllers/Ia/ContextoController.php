<?php

namespace App\Http\Controllers\Ia;

use App\Enums\IaIntent;
use App\Http\Controllers\Controller;
use App\Models\Doctor;
use App\Services\Ia\MontadorDeContexto;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * O n8n chama isto ANTES de gerar a resposta: recebe o prompt publicado no
 * painel, as regras inegociáveis, os exemplos aprovados e os cards da base.
 *
 * Assim o fluxo do n8n deixa de ter prompt hardcoded: a fonte da verdade das
 * instruções passa a ser o painel, versionada e auditável.
 */
class ContextoController extends Controller
{
    public function __invoke(Request $request, MontadorDeContexto $montador): JsonResponse
    {
        $dados = $request->validate([
            'agente' => ['required', 'string', 'max:20'],
            'intent' => ['nullable', 'string', 'max:30'],
            // direct (conversa completa) ou comentario (resposta curta a quem comentou num post)
            'canal' => ['nullable', 'string', 'in:direct,comentario'],
        ]);

        $doctor = Doctor::query()->where('agente', $dados['agente'])->first();

        if ($doctor === null) {
            return response()->json(['erro' => 'agente desconhecido'], 404);
        }

        $intent = filled($dados['intent'] ?? null)
            ? IaIntent::tryFrom((string) $dados['intent'])
            : null;

        $canal = (string) ($dados['canal'] ?? 'direct');

        return response()->json($montador->paraAgente($doctor, $intent, $canal));
    }
}
