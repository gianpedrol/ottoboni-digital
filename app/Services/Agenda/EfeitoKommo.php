<?php

namespace App\Services\Agenda;

/**
 * O que a agenda mandaria para o Kommo. No protótipo é só descrito na tela;
 * nada é enviado.
 */
final readonly class EfeitoKommo
{
    /**
     * @param  array<string, mixed>  $corpo  body do PATCH
     * @param  array<int, string>  $mudancas  linhas legíveis ("Comparecimento = Faltou")
     * @param  array<string, string>  $automacoes  código => o que a automação faz
     */
    public function __construct(
        public string $evento,
        public int $appointmentId,
        public ?int $leadId,
        public array $corpo,
        public array $mudancas,
        public array $automacoes,
        public bool $simulado,
        public bool $abrirProntuario = false,
    ) {}

    public function temEnvio(): bool
    {
        return $this->leadId !== null && $this->corpo !== [];
    }

    public function metodo(): string
    {
        return 'PATCH';
    }

    public function endpoint(): string
    {
        return "/api/v4/leads/{$this->leadId}";
    }

    public function titulo(): string
    {
        if (! $this->temEnvio()) {
            return 'Nada a enviar ao Kommo';
        }

        return $this->simulado
            ? 'Simulado — no painel real o Kommo seria atualizado'
            : 'Kommo não atualizado — envio pela agenda ainda desligado';
    }

    public function texto(): string
    {
        if ($this->leadId === null) {
            return 'Este paciente não tem lead vinculado no Kommo, então nenhum campo seria alterado.';
        }

        if (! $this->temEnvio()) {
            return 'Esta ação não altera nada no Kommo.';
        }

        $linhas = [$this->metodo() . ' ' . $this->endpoint() . ': ' . implode(' · ', $this->mudancas) . '.'];

        foreach ($this->automacoes as $codigo => $descricao) {
            $linhas[] = "Isso dispara a automação {$codigo} do Kommo: {$descricao}";
        }

        if ($this->automacoes !== []) {
            $linhas[] = '(Catálogo de automações: docs/escopo-fase-3.md, seção 5.)';
        }

        return implode("\n", $linhas);
    }

    /**
     * @return array<string, mixed>
     */
    public function paraLog(): array
    {
        return [
            'evento' => $this->evento,
            'appointment_id' => $this->appointmentId,
            'lead_id' => $this->leadId,
            'metodo' => $this->metodo(),
            'endpoint' => $this->leadId !== null ? $this->endpoint() : null,
            'corpo' => $this->corpo,
            'automacoes' => array_keys($this->automacoes),
            'simulado' => $this->simulado,
        ];
    }
}
