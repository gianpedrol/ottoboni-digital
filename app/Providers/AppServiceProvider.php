<?php

namespace App\Providers;

use App\Repositories\KommoLeadRepository;
use App\Repositories\LeadRepository;
use App\Services\Kommo\CustomFieldMap;
use App\Services\Kommo\KommoClient;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(KommoClient::class);
        $this->app->singleton(CustomFieldMap::class);

        // Interface no meio para permitir o plano B (lead_snapshots) com
        // troca de uma linha, se o relatório mensal passar de ~30s.
        $this->app->bind(LeadRepository::class, KommoLeadRepository::class);
    }

    public function boot(): void
    {
        // As tabelas de leads usam arrays vindos da API do Kommo; a chave
        // de linha é o id do lead.
        \Filament\Support\ArrayRecord::keyName('id');
    }
}
