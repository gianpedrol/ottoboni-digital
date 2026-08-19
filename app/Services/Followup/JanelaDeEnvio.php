<?php

namespace App\Services\Followup;

use App\Models\Setting;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Janela de horário permitido para envio (padrão 09:00–20:00, seg–sáb,
 * ajustável na tela Configurações). Fora dela nada sai: o envio é adiado
 * para o próximo horário permitido.
 */
class JanelaDeEnvio
{
    public static function dentro(CarbonInterface $momento): bool
    {
        $config = self::config();
        $local = CarbonImmutable::instance($momento)->setTimezone(config('painel.timezone'));

        if (! in_array($local->isoWeekday(), $config['dias'], true)) {
            return false;
        }

        $inicio = $local->setTimeFromTimeString($config['inicio']);
        $fim = $local->setTimeFromTimeString($config['fim']);

        return $local->gte($inicio) && $local->lte($fim);
    }

    /**
     * Próximo momento permitido a partir de $momento (o próprio, se já
     * estiver dentro da janela). Sempre devolve em UTC — é o que vai
     * para o banco; a conversão de exibição é responsabilidade da tela.
     */
    public static function proxima(CarbonInterface $momento): CarbonImmutable
    {
        $config = self::config();
        $local = CarbonImmutable::instance($momento)->setTimezone(config('painel.timezone'));

        for ($i = 0; $i <= 8; $i++) {
            $dia = $local->addDays($i);
            $inicio = $dia->setTimeFromTimeString($config['inicio']);
            $fim = $dia->setTimeFromTimeString($config['fim']);

            if (! in_array($dia->isoWeekday(), $config['dias'], true)) {
                continue;
            }

            if ($i === 0 && $local->gte($inicio) && $local->lte($fim)) {
                return $local->utc();
            }

            if ($i === 0 && $local->lt($inicio)) {
                return $inicio->utc();
            }

            if ($i > 0) {
                return $inicio->utc();
            }
        }

        // Configuração sem dia permitido — devolve o momento para não
        // travar a fila; o executor vai adiar de novo.
        return $local->utc();
    }

    /**
     * @return array{inicio: string, fim: string, dias: array<int>}
     */
    private static function config(): array
    {
        $config = Setting::get('janela_envio', config('painel.janela_envio'));

        return [
            'inicio' => $config['inicio'] ?? '09:00',
            'fim' => $config['fim'] ?? '20:00',
            'dias' => array_map(intval(...), $config['dias'] ?? [1, 2, 3, 4, 5, 6]),
        ];
    }
}
