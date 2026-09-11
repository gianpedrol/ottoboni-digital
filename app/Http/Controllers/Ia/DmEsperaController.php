<?php

namespace App\Http\Controllers\Ia;

use App\Http\Controllers\Controller;
use App\Models\IaApproval;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * O n8n confirma que o DM de espera saiu ("já te respondo em seguida").
 * Fica em rota separada porque o DM de espera é enviado antes de qualquer
 * decisão humana — é só acolhimento, não é resposta.
 */
class DmEsperaController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $dados = $request->validate([
            'approval_id' => ['required', 'integer'],
            'enviado' => ['required', 'boolean'],
        ]);

        IaApproval::query()
            ->whereKey($dados['approval_id'])
            ->update(['dm_espera_enviada' => $dados['enviado']]);

        return response()->json(['ok' => true]);
    }
}
