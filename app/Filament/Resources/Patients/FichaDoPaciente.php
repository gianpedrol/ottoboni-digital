<?php

namespace App\Filament\Resources\Patients;

use App\Models\Appointment;
use App\Models\Patient;
use App\Models\Receivable;
use App\Models\ReceivableInstallment;
use BackedEnum;
use Filament\Support\Contracts\HasLabel;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

/**
 * Consultas e formatação usadas pelas abas da ficha do paciente.
 * Agenda e Financeiro são de outros módulos: aqui só leitura.
 */
class FichaDoPaciente
{
    public static function tz(): string
    {
        return (string) config('painel.timezone');
    }

    /**
     * Rótulo legível para status/tipos que podem vir como string ou enum.
     */
    public static function rotulo(mixed $valor): string
    {
        if ($valor instanceof HasLabel) {
            return (string) $valor->getLabel();
        }

        if ($valor instanceof BackedEnum) {
            return (string) $valor->value;
        }

        if ($valor === null || $valor === '') {
            return '—';
        }

        return Str::ucfirst(str_replace('_', ' ', (string) $valor));
    }

    public static function valorBruto(mixed $valor): string
    {
        return $valor instanceof BackedEnum ? (string) $valor->value : (string) $valor;
    }

    /**
     * @return Collection<int, Appointment>
     */
    public static function agendamentos(Patient $patient, bool $somenteFuturos = false): Collection
    {
        $query = $patient->appointments()->with(['doctor', 'service']);

        if ($somenteFuturos) {
            $query->where('inicio', '>=', now())
                ->whereNotIn('status', Patient::statusAgendaInativos())
                ->orderBy('inicio')
                ->limit(5);
        } else {
            $query->orderByDesc('inicio');
        }

        return $query->get();
    }

    public static function corStatusAgenda(mixed $status): string
    {
        return match (self::valorBruto($status)) {
            'agendado' => 'info',
            'confirmado' => 'success',
            'realizado' => 'gray',
            'faltou' => 'danger',
            'remarcado' => 'warning',
            default => 'gray',
        };
    }

    /**
     * @return array{recebiveis: Collection<int, Receivable>, total: float, pago: float, aberto: float, atrasado: float}
     */
    public static function financeiro(Patient $patient): array
    {
        $recebiveis = $patient->receivables()->with(['installments', 'service', 'doctor'])->latest()->get();

        $total = $pago = $aberto = $atrasado = 0.0;

        foreach ($recebiveis as $recebivel) {
            foreach ($recebivel->installments as $parcela) {
                $status = self::statusParcela($parcela);

                if ($status === 'cancelado') {
                    continue;
                }

                $total += (float) $parcela->valor;

                if ($status === 'pago') {
                    $pago += (float) ($parcela->valor_pago ?? $parcela->valor);

                    continue;
                }

                $aberto += (float) $parcela->valor;

                if ($status === 'atrasado') {
                    $atrasado += (float) $parcela->valor;
                }
            }
        }

        return compact('recebiveis', 'total', 'pago', 'aberto', 'atrasado');
    }

    /**
     * Parcela em aberto com vencimento passado conta como atrasada.
     */
    public static function statusParcela(ReceivableInstallment $parcela): string
    {
        $status = self::valorBruto($parcela->status);

        if ($status === 'aberto' && $parcela->vencimento !== null && $parcela->vencimento->lt(today())) {
            return 'atrasado';
        }

        return $status;
    }

    public static function corStatusParcela(string $status): string
    {
        return match ($status) {
            'pago' => 'success',
            'atrasado' => 'danger',
            'aberto' => 'warning',
            default => 'gray',
        };
    }

    public static function moeda(float|string|null $valor): string
    {
        return 'R$ ' . number_format((float) $valor, 2, ',', '.');
    }

    public static function json(mixed $dados): string
    {
        return (string) json_encode($dados, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
