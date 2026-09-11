<?php

namespace App\Support;

/**
 * Segredos que o painel compartilha com o mundo de fora (n8n e cron da
 * hospedagem). Podem vir do .env; quando não vêm, são derivados da APP_KEY,
 * para a instalação não depender de mais uma variável configurada à mão.
 *
 * Derivar da APP_KEY é seguro o suficiente aqui: quem tem a APP_KEY já abre
 * sessões e dados cifrados — o segredo do webhook não é um elo mais fraco.
 * Trocar a APP_KEY troca os dois segredos (e exige recolar no n8n e no cron).
 */
class SegredosDoPainel
{
    /** HMAC das duas pontas painel <-> n8n (área de IA). */
    public static function webhookIa(): string
    {
        $env = (string) config('painel.ia.webhook_secret');

        return $env !== '' ? $env : self::derivar('ia-webhook');
    }

    public static function webhookIaVeioDoEnv(): bool
    {
        return filled(config('painel.ia.webhook_secret'));
    }

    /** Token da rota GET /cron/{token}. */
    public static function cronToken(): string
    {
        $env = (string) config('painel.cron_token');

        return $env !== '' ? $env : substr(self::derivar('cron-token'), 0, 48);
    }

    /** URL completa que a hospedagem deve chamar a cada minuto. */
    public static function cronUrl(): string
    {
        return url('/cron/'.self::cronToken());
    }

    private static function derivar(string $finalidade): string
    {
        $chave = (string) config('app.key');

        if (str_starts_with($chave, 'base64:')) {
            $chave = (string) base64_decode(substr($chave, 7), true);
        }

        if ($chave === '') {
            // Sem APP_KEY não existe segredo confiável; o chamador trata como
            // "não configurado" (assinatura nunca bate, cron responde 404).
            return '';
        }

        return hash_hmac('sha256', 'painel-ottoboni:'.$finalidade, $chave);
    }
}
