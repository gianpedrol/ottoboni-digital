<?php

namespace App\Services\Financeiro;

use App\Models\BankAccount;
use Carbon\CarbonImmutable;

/**
 * Leitor simples de OFX (o SGML que os bancos exportam): pega os blocos
 * <STMTTRN> e os campos DTPOSTED, TRNAMT, FITID e MEMO. FITID já
 * importado na mesma conta é ignorado, então reimportar o arquivo é seguro.
 */
class ImportadorOfx
{
    /**
     * @return array<int, array{data: string, valor: float, fitid: string, descricao: string}>
     */
    public function ler(string $conteudo): array
    {
        // Bancos brasileiros costumam exportar em Windows-1252.
        if (! mb_check_encoding($conteudo, 'UTF-8')) {
            $conteudo = mb_convert_encoding($conteudo, 'UTF-8', 'Windows-1252');
        }

        // No SGML o </STMTTRN> é opcional: corta pelo início de cada bloco.
        $blocos = preg_split('/<STMTTRN>/i', $conteudo) ?: [];
        array_shift($blocos);

        $lancamentos = [];

        foreach ($blocos as $bloco) {
            $bloco = preg_split('/<\/STMTTRN>|<\/BANKTRANLIST>/i', $bloco)[0] ?? $bloco;

            $data = $this->campo($bloco, 'DTPOSTED');
            $valor = $this->campo($bloco, 'TRNAMT');

            if ($data === null || $valor === null || ! preg_match('/^\d{8}/', $data)) {
                continue;
            }

            $valor = (float) str_replace(',', '.', $valor);
            $dia = CarbonImmutable::createFromFormat('Ymd', substr($data, 0, 8))->toDateString();
            $descricao = $this->campo($bloco, 'MEMO') ?? $this->campo($bloco, 'NAME') ?? 'Lançamento sem descrição';

            // Sem FITID, uma chave derivada ainda evita duplicar na reimportação.
            $fitid = $this->campo($bloco, 'FITID') ?? 'SEM-FITID-' . md5($dia . $valor . $descricao);

            $lancamentos[] = [
                'data' => $dia,
                'valor' => round($valor, 2),
                'fitid' => $fitid,
                'descricao' => mb_substr($descricao, 0, 255),
            ];
        }

        return $lancamentos;
    }

    /**
     * @return array{importados: int, ignorados: int}
     */
    public function importar(BankAccount $conta, string $conteudo): array
    {
        /** @var array<string, true> $existentes */
        $existentes = array_fill_keys($conta->transactions()->whereNotNull('fitid')->pluck('fitid')->all(), true);

        $importados = 0;
        $ignorados = 0;

        foreach ($this->ler($conteudo) as $lancamento) {
            if (isset($existentes[$lancamento['fitid']])) {
                $ignorados++;

                continue;
            }

            $conta->transactions()->create([
                ...$lancamento,
                'demo' => true,
            ]);

            $existentes[$lancamento['fitid']] = true;
            $importados++;
        }

        return ['importados' => $importados, 'ignorados' => $ignorados];
    }

    private function campo(string $bloco, string $tag): ?string
    {
        if (! preg_match('/<' . $tag . '>([^<\r\n]*)/i', $bloco, $m)) {
            return null;
        }

        $valor = trim(html_entity_decode($m[1], ENT_QUOTES | ENT_XML1, 'UTF-8'));

        return $valor === '' ? null : $valor;
    }
}
