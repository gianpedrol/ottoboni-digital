<?php

namespace App\Services\Agenda;

use App\Enums\EtapaCanonica;
use App\Enums\StatusAgendamento;
use App\Models\Appointment;
use App\Models\AuditLog;
use App\Support\MapaDeEtapas;

/**
 * Monta o PATCH /api/v4/leads/{id} que a agenda faria no Kommo.
 *
 * Esta classe NUNCA envia nada: não usa KommoClient nem KommoActions. No
 * protótipo o efeito é mostrado ao usuário e registrado na auditoria. Os
 * campos vão pelo nome; no envio real o ID seria resolvido pelo
 * CustomFieldMap (GET /leads/custom_fields).
 */
class EfeitosNoKommo
{
    public function aoAgendar(Appointment $agendamento): EfeitoKommo
    {
        $campos = [
            $this->campo('proxima_consulta', $agendamento->inicio->getTimestamp()),
        ];

        $mudancas = [
            'Próxima consulta = ' . $agendamento->inicioLocal()->format('d/m/Y H:i'),
        ];

        return $this->montar(
            agendamento: $agendamento,
            evento: 'agendado',
            campos: $campos,
            mudancas: $mudancas,
            moverParaAgendada: true,
            automacoes: [
                'A2' => '"Agendou no FUP" — com a Próxima consulta preenchida, tira as tags de FUP (Contato1/2/3) e cancela os follow-ups pendentes do lead.',
            ],
        );
    }

    public function aoRemarcar(Appointment $novo, Appointment $antigo): EfeitoKommo
    {
        $campos = [
            $this->campo('proxima_consulta', $novo->inicio->getTimestamp()),
            $this->campo('comparecimento', 'Remarcou'),
        ];

        $mudancas = [
            'Próxima consulta = ' . $novo->inicioLocal()->format('d/m/Y H:i')
                . ' (era ' . $antigo->inicioLocal()->format('d/m/Y H:i') . ')',
            'Comparecimento = Remarcou',
        ];

        return $this->montar(
            agendamento: $novo,
            evento: 'remarcado',
            campos: $campos,
            mudancas: $mudancas,
            moverParaAgendada: true,
            automacoes: [
                'A2' => '"Agendou no FUP" — a nova data em Próxima consulta mantém o lead em "Consulta agendada" e sem follow-up pendente.',
            ],
        );
    }

    public function aoMudarStatus(Appointment $agendamento, StatusAgendamento $status): ?EfeitoKommo
    {
        return match ($status) {
            StatusAgendamento::Realizado => $this->montar(
                agendamento: $agendamento,
                evento: 'realizado',
                campos: [$this->campo('comparecimento', 'Compareceu')],
                mudancas: ['Comparecimento = Compareceu'],
                automacoes: [
                    'A3' => '"Consulta realizada" — move o lead para "Realizou consulta". Depois, com o Valor da proposta preenchido, a A4 leva para "Orçamento / negociação".',
                ],
                abrirProntuario: true,
            ),
            StatusAgendamento::Faltou => $this->montar(
                agendamento: $agendamento,
                evento: 'faltou',
                campos: [$this->campo('comparecimento', 'Faltou')],
                mudancas: ['Comparecimento = Faltou'],
                automacoes: [
                    'A5' => '"No-show" — move o lead para "Não agendou", põe a tag RESGATECONSULTA e inicia a régua de resgate.',
                ],
            ),
            StatusAgendamento::Cancelado => $this->montar(
                agendamento: $agendamento,
                evento: 'cancelado',
                campos: [$this->campo('proxima_consulta', null)],
                mudancas: ['Próxima consulta = (limpo)'],
                automacoes: [],
            ),
            default => null,
        };
    }

    /**
     * Registra na auditoria o que seria enviado (sem PII: só ids e campos).
     */
    public function registrar(EfeitoKommo $efeito): void
    {
        AuditLog::registrar($efeito->simulado ? 'simulou_kommo_agenda' : 'kommo_agenda_pendente', $efeito->paraLog());
    }

    /**
     * @param  array<int, array<string, mixed>>  $campos
     * @param  array<int, string>  $mudancas
     * @param  array<string, string>  $automacoes
     */
    private function montar(
        Appointment $agendamento,
        string $evento,
        array $campos,
        array $mudancas,
        array $automacoes,
        bool $moverParaAgendada = false,
        bool $abrirProntuario = false,
    ): EfeitoKommo {
        $leadId = $agendamento->kommo_lead_id;

        if ($leadId === null) {
            return new EfeitoKommo(
                evento: $evento,
                appointmentId: (int) $agendamento->getKey(),
                leadId: null,
                corpo: [],
                mudancas: [],
                automacoes: [],
                simulado: (bool) config('painel.prototipo'),
                abrirProntuario: $abrirProntuario,
            );
        }

        $corpo = [];

        if ($moverParaAgendada) {
            $pipelineId = (int) $agendamento->doctor?->kommo_pipeline_id;
            $statusId = MapaDeEtapas::destino($pipelineId, EtapaCanonica::ConsultaAgendada);

            if ($statusId !== null) {
                $corpo['pipeline_id'] = $pipelineId;
                $corpo['status_id'] = $statusId;
                $mudancas[] = 'Etapa = ' . EtapaCanonica::ConsultaAgendada->getLabel() . " (status {$statusId})";
            }
        }

        $corpo['custom_fields_values'] = $campos;

        return new EfeitoKommo(
            evento: $evento,
            appointmentId: (int) $agendamento->getKey(),
            leadId: $leadId,
            corpo: $corpo,
            mudancas: $mudancas,
            automacoes: $automacoes,
            simulado: (bool) config('painel.prototipo'),
            abrirProntuario: $abrirProntuario,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function campo(string $chave, int|string|null $valor): array
    {
        return [
            'field_id' => null,
            'field_name' => (string) config("kommo.custom_fields.{$chave}"),
            'values' => $valor === null ? null : [['value' => $valor]],
        ];
    }
}
