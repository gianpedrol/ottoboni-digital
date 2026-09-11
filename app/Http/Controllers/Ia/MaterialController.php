<?php

namespace App\Http\Controllers\Ia;

use App\Http\Controllers\Controller;
use App\Models\IaMaterial;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Entrega o arquivo de um material pela URL pública com token. É o endereço
 * que vai para o Instagram buscar a imagem — por isso não tem login, e por
 * isso o token é longo e aleatório.
 */
class MaterialController extends Controller
{
    public function __invoke(string $token, string $nome): StreamedResponse
    {
        /** @var IaMaterial|null $material */
        $material = IaMaterial::query()
            ->where('token', $token)
            ->where('ativo', true)
            ->whereNotNull('arquivo')
            ->first();

        if ($material === null || basename((string) $material->arquivo) !== $nome) {
            abort(404);
        }

        $disco = Storage::disk('public');

        if (! $disco->exists((string) $material->arquivo)) {
            abort(404);
        }

        return $disco->response((string) $material->arquivo, $nome, [
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }
}
