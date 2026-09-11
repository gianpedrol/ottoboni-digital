<?php

namespace App\Services\Ia;

use App\Enums\IaGrauEdicao;

/**
 * Mede o quanto o humano precisou mexer no rascunho da agente.
 *
 * Essa é a definição de acurácia do projeto: não é a IA se avaliando nem um
 * juiz LLM opinando — é o trabalho que ela deu para a equipe. Aprovado sem
 * tocar vale 1,0; refeito do zero vale 0.
 *
 * A comparação é feita sobre o texto normalizado (minúsculas, sem acento, sem
 * pontuação, espaços colapsados) para que "Olá! 💛" e "ola" não contem como
 * edição de conteúdo.
 */
class MedidorDeEdicao
{
    /** A partir daqui consideramos que o humano não mudou o conteúdo. */
    private const LIMITE_SEM_EDICAO = 0.995;

    private const LIMITE_LEVE = 0.850;

    private const LIMITE_MEDIA = 0.550;

    /**
     * @return array{similaridade: float, grau: IaGrauEdicao, score: float}
     */
    public function medir(?string $rascunho, ?string $final): array
    {
        $a = $this->normalizar($rascunho);
        $b = $this->normalizar($final);

        if ($a === '' && $b === '') {
            // Nada proposto e nada enviado: não há o que pontuar.
            return $this->resultado(0.0, IaGrauEdicao::Refeita);
        }

        if ($a === '') {
            // A agente não produziu nada (caso "não sei") — o humano escreveu tudo.
            return $this->resultado(0.0, IaGrauEdicao::Refeita);
        }

        if ($a === $b) {
            return $this->resultado(1.0, IaGrauEdicao::SemEdicao);
        }

        $similaridade = $this->similaridade($a, $b);

        $grau = match (true) {
            $similaridade >= self::LIMITE_SEM_EDICAO => IaGrauEdicao::SemEdicao,
            $similaridade >= self::LIMITE_LEVE => IaGrauEdicao::Leve,
            $similaridade >= self::LIMITE_MEDIA => IaGrauEdicao::Media,
            default => IaGrauEdicao::Refeita,
        };

        return $this->resultado($similaridade, $grau);
    }

    /**
     * Combina duas visões para não ser enganado por reordenação de frases nem
     * por troca de palavras: similaridade de caracteres (similar_text) e
     * Jaccard sobre as palavras. Fica com a menor — na dúvida, assume que
     * houve mais edição, nunca menos.
     */
    private function similaridade(string $a, string $b): float
    {
        similar_text($a, $b, $percentual);
        $caracteres = $percentual / 100;

        $palavrasA = array_unique(explode(' ', $a));
        $palavrasB = array_unique(explode(' ', $b));

        $intersecao = count(array_intersect($palavrasA, $palavrasB));
        $uniao = count(array_unique(array_merge($palavrasA, $palavrasB)));

        $jaccard = $uniao === 0 ? 0.0 : $intersecao / $uniao;

        return round(min($caracteres, $jaccard), 3);
    }

    private function normalizar(?string $texto): string
    {
        $t = mb_strtolower(trim((string) $texto));

        if ($t === '') {
            return '';
        }

        // Remove acentos sem depender da extensão intl.
        $t = strtr($t, [
            'á' => 'a', 'à' => 'a', 'ã' => 'a', 'â' => 'a', 'ä' => 'a',
            'é' => 'e', 'ê' => 'e', 'è' => 'e', 'ë' => 'e',
            'í' => 'i', 'î' => 'i', 'ì' => 'i',
            'ó' => 'o', 'õ' => 'o', 'ô' => 'o', 'ò' => 'o', 'ö' => 'o',
            'ú' => 'u', 'û' => 'u', 'ù' => 'u', 'ü' => 'u',
            'ç' => 'c', 'ñ' => 'n',
        ]);

        // Fora pontuação e emoji: mudança de emoji não é mudança de conteúdo.
        $t = (string) preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $t);

        return trim((string) preg_replace('/\s+/u', ' ', $t));
    }

    /**
     * @return array{similaridade: float, grau: IaGrauEdicao, score: float}
     */
    private function resultado(float $similaridade, IaGrauEdicao $grau): array
    {
        return [
            'similaridade' => round($similaridade, 3),
            'grau' => $grau,
            'score' => $grau->score(),
        ];
    }
}
