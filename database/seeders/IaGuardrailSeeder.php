<?php

namespace Database\Seeders;

use App\Models\IaGuardrail;
use Illuminate\Database\Seeder;

/**
 * As regras que não se negociam. Vão sempre no fim do system prompt e não
 * aparecem como campo editável no painel — mexer nelas é decisão clínica.
 *
 * doctor_id nulo = valem para as duas agentes.
 */
class IaGuardrailSeeder extends Seeder
{
    public function run(): void
    {
        $regras = [
            'Nunca dar diagnóstico, prescrever tratamento ou indicar medicamento.',
            'Nunca prometer resultado, prazo de melhora ou garantia de cura.',
            'Nunca inventar informação que não esteja na base de cards validados. Sem card, responder que vai verificar e escalar para a equipe.',
            'Valores sempre no formato R$ 000,00, exatamente como está na base.',
            'Nunca forçar agendamento. Informar e deixar a paciente decidir.',
            'Teste genético e programa Pleno: a coleta é presencial e só em Curitiba.',
            'Assunto fora dos caminhos de teste genético e consulta: não improvisar, a equipe assume.',
            'Comentário específico (dica, relato, opinião) ou comentário longo: silêncio total — nem resposta pública, nem direct.',
            'Elogio genérico curto: responder emoji com emoji, sem puxar assunto comercial.',
            'Nunca responder quando um humano da equipe já está atendendo a conversa (janela de silêncio de 6 horas).',
        ];

        foreach ($regras as $ordem => $regra) {
            IaGuardrail::query()->updateOrCreate(
                ['doctor_id' => null, 'regra' => $regra],
                ['ordem' => $ordem + 1, 'ativo' => true],
            );
        }
    }
}
