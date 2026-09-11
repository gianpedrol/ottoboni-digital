<?php

namespace App\Services\Pacientes;

use App\Enums\EtapaCanonica;
use App\Enums\KommoSyncStatus;
use App\Models\AuditLog;
use App\Models\Patient;
use App\Support\MapaDeEtapas;
use App\Support\Telefone;

/**
 * Leva o cadastro do paciente para o Kommo: contato e, se pedido, um lead
 * no pipeline do médico, na etapa de entrada.
 *
 * Nesta fase NADA é enviado. Em modo protótipo o serviço monta o corpo
 * exato das requisições, guarda em kommo_sync_payload e marca "simulado".
 * O envio real (via KommoClient, em job com nova tentativa) entra quando
 * o módulo sair do protótipo.
 */
class SincronizadorKommo
{
    /**
     * Placeholder do id do contato quando ele ainda não existe: na versão
     * real o lead é criado com o id devolvido pelo POST /contacts.
     */
    public const ID_CONTATO_A_CRIAR = '{id do contato criado na requisição anterior}';

    /**
     * Duplicidade por telefone. No protótipo olha só o banco local; a
     * versão real faz antes GET /api/v4/contacts?query=<telefone> e, se
     * achar, vincula o contato existente em vez de criar outro.
     */
    public function buscarDuplicado(?string $telefone, ?int $ignorarId = null): ?Patient
    {
        $normalizado = Telefone::normalizar($telefone);

        if ($normalizado === null) {
            return null;
        }

        return Patient::query()
            ->where('telefone', $normalizado)
            ->when($ignorarId, fn ($q) => $q->whereKeyNot($ignorarId))
            ->first();
    }

    public function sincronizar(Patient $patient, bool $abrirAtendimento = false): ResultadoSincronizacao
    {
        $payload = $this->montarPayload($patient, $abrirAtendimento);
        $criaLead = count($payload['requisicoes']) > 1;

        if (! config('painel.prototipo')) {
            // Fora do protótipo o envio real ainda não está ligado: o
            // paciente fica pendente e nada sai para o Kommo.
            $patient->forceFill([
                'kommo_sync_status' => KommoSyncStatus::Pendente,
                'kommo_sync_payload' => $payload,
            ])->save();

            return new ResultadoSincronizacao(
                KommoSyncStatus::Pendente,
                'Paciente salvo — pendente de sincronizar',
                'O envio ao Kommo ainda não está habilitado neste painel.',
            );
        }

        $patient->forceFill([
            'kommo_sync_status' => KommoSyncStatus::Simulado,
            'kommo_sync_payload' => $payload,
        ])->save();

        AuditLog::registrar('simulou_sync_kommo', [
            'patient_id' => $patient->id,
            'cria_lead' => $criaLead,
        ]);

        $acao = $patient->kommo_contact_id ? 'atualizaria o contato' : 'criaria o contato';

        return new ResultadoSincronizacao(
            KommoSyncStatus::Simulado,
            'Simulado — no painel real isto ' . $acao . ' no Kommo',
            $criaLead
                ? 'E abriria um atendimento no funil do médico, na etapa de entrada. O que seria enviado está na aba Kommo do paciente.'
                : 'O que seria enviado está na aba Kommo do paciente.',
        );
    }

    /**
     * @return array{modo: string, gerado_em: string, requisicoes: array<int, array<string, mixed>>, observacoes: array<int, string>}
     */
    public function montarPayload(Patient $patient, bool $abrirAtendimento = false): array
    {
        $requisicoes = [$this->requisicaoContato($patient)];
        $observacoes = [
            'Antes de criar, a versão real consulta GET /api/v4/contacts?query=' . Telefone::e164($patient->telefone) . ' para não duplicar o contato.',
            'CPF não é enviado ao Kommo (fica só no painel, cifrado).',
        ];

        if ($abrirAtendimento && $patient->kommo_lead_id === null) {
            $lead = $this->requisicaoLead($patient);

            if ($lead !== null) {
                $requisicoes[] = $lead;
            } else {
                $observacoes[] = 'Atendimento não montado: paciente sem médico com pipeline configurado.';
            }
        }

        return [
            'modo' => config('painel.prototipo') ? 'simulado' : 'pendente',
            'gerado_em' => now()->toIso8601String(),
            'requisicoes' => $requisicoes,
            'observacoes' => $observacoes,
        ];
    }

    /**
     * Corpo de POST /api/v4/contacts (ou PATCH, se já vinculado).
     *
     * @return array<string, mixed>
     */
    public function requisicaoContato(Patient $patient): array
    {
        [$primeiro, $sobrenome] = $this->separarNome($patient->nome);

        $campos = [[
            'field_code' => 'PHONE',
            'values' => [['value' => Telefone::e164($patient->telefone), 'enum_code' => 'WORK']],
        ]];

        if (filled($patient->email)) {
            $campos[] = [
                'field_code' => 'EMAIL',
                'values' => [['value' => $patient->email, 'enum_code' => 'WORK']],
            ];
        }

        $contato = [
            'name' => $patient->nome,
            'first_name' => $primeiro,
            'last_name' => $sobrenome,
            'custom_fields_values' => $campos,
        ];

        if ($patient->kommo_contact_id) {
            return [
                'metodo' => 'PATCH',
                'endpoint' => '/api/v4/contacts',
                'corpo' => [['id' => $patient->kommo_contact_id, ...$contato]],
            ];
        }

        return [
            'metodo' => 'POST',
            'endpoint' => '/api/v4/contacts',
            'corpo' => [$contato],
        ];
    }

    /**
     * Corpo de POST /api/v4/leads no pipeline do médico, etapa "Novo".
     *
     * @return array<string, mixed>|null
     */
    public function requisicaoLead(Patient $patient): ?array
    {
        $pipelineId = (int) $patient->doctor?->kommo_pipeline_id;

        if ($pipelineId === 0) {
            return null;
        }

        $lead = [
            'name' => trim($patient->nome . ($patient->procedimento_interesse ? ' — ' . $patient->procedimento_interesse : '')),
            'pipeline_id' => $pipelineId,
        ];

        // Sem etapa mapeada o Kommo usa a primeira do pipeline.
        $statusId = MapaDeEtapas::destino($pipelineId, EtapaCanonica::Novo);

        if ($statusId !== null) {
            $lead['status_id'] = $statusId;
        }

        $lead['_embedded'] = [
            'contacts' => [['id' => $patient->kommo_contact_id ?? self::ID_CONTATO_A_CRIAR]],
        ];

        return [
            'metodo' => 'POST',
            'endpoint' => '/api/v4/leads',
            'corpo' => [$lead],
        ];
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function separarNome(string $nome): array
    {
        $partes = preg_split('/\s+/', trim($nome)) ?: [$nome];
        $primeiro = array_shift($partes) ?? $nome;

        return [$primeiro, implode(' ', $partes)];
    }
}
