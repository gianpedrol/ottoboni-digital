<?php

use App\Filament\Resources\FollowupPlans\Schemas\FollowupPlanForm;

it('sem n8n configurado, só oferece Salesbot e tarefa, e só texto fixo', function () {
    config()->set('painel.n8n.followup_url', null);
    config()->set('painel.n8n.webhook_secret', null);

    expect(array_keys(FollowupPlanForm::canaisDisponiveis()))->toBe(['kommo_bot', 'kommo_task'])
        ->and(array_keys(FollowupPlanForm::modosDisponiveis()))->toBe(['texto_fixo']);
});

it('com n8n configurado, todos os canais e modos aparecem', function () {
    config()->set('painel.n8n.followup_url', 'https://n8n.local/webhook/followup');
    config()->set('painel.n8n.webhook_secret', 'segredo');

    expect(array_keys(FollowupPlanForm::canaisDisponiveis()))
        ->toBe(['auto', 'instagram', 'kommo_bot', 'kommo_task'])
        ->and(array_keys(FollowupPlanForm::modosDisponiveis()))->toBe(['texto_fixo', 'ia']);
});
