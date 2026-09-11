<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Área de treinamento das agentes de IA (Duda e Luna).
 *
 * A regra central: a agente nunca envia nada que um humano não tenha liberado,
 * até provar acurácia. E "não sei responder" nunca é liberado — é aí que a
 * base de conhecimento cresce.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Prompt versionado. Publicar exige aceite de responsabilidade.
        Schema::create('ia_prompt_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('doctor_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('versao');
            $table->json('blocos');
            $table->boolean('ativo')->default(false);
            $table->foreignId('autor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('motivo')->nullable();
            $table->boolean('aceite_responsabilidade')->default(false);
            $table->text('aceite_texto')->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamps();

            $table->unique(['doctor_id', 'versao']);
            $table->index(['doctor_id', 'ativo']);
        });

        // Fila de aprovação: 1 linha = 1 interação que a agente quer responder.
        Schema::create('ia_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('doctor_id')->constrained()->cascadeOnDelete();
            $table->string('canal', 20);                 // comentario | direct
            $table->string('intent', 30)->default('indefinido');
            $table->decimal('confianca', 4, 3)->nullable();
            $table->string('motivo_fila', 30);

            // Contexto do Instagram — é o que o humano lê para decidir.
            $table->string('ig_id', 64)->nullable();
            $table->string('ig_username', 120)->nullable();
            $table->string('post_id', 64)->nullable();
            $table->string('post_permalink')->nullable();
            $table->text('post_caption')->nullable();
            $table->string('post_media_url', 1024)->nullable();
            $table->string('comment_id', 64)->nullable();
            $table->text('comentario_texto')->nullable();
            $table->dateTime('comentario_em')->nullable();
            $table->text('mensagem_texto')->nullable();
            $table->json('historico')->nullable();

            // O que a agente propôs / o que o humano mandou de verdade.
            $table->text('rascunho_comentario')->nullable();
            $table->text('rascunho_dm')->nullable();
            $table->text('final_comentario')->nullable();
            $table->text('final_dm')->nullable();

            // Rastreabilidade da geração.
            $table->string('modelo', 60)->nullable();
            $table->foreignId('prompt_version_id')->nullable()->constrained('ia_prompt_versions')->nullOnDelete();
            $table->json('cards_usados')->nullable();
            $table->unsignedInteger('tokens_prompt')->nullable();
            $table->unsignedInteger('tokens_resposta')->nullable();

            // Revisão humana.
            $table->string('status', 20)->default('pendente');
            $table->string('grau_edicao', 20)->nullable();
            $table->decimal('similaridade', 4, 3)->nullable();
            $table->decimal('score', 4, 3)->nullable();
            $table->foreignId('revisado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('revisado_em')->nullable();
            $table->text('observacao_humano')->nullable();
            $table->boolean('responsabilidade_aceita')->default(false);

            // Operação.
            $table->boolean('dm_espera_enviada')->default(false);
            $table->dateTime('notificado_em')->nullable();
            $table->dateTime('expira_em')->nullable();
            $table->dateTime('enviado_em')->nullable();
            $table->text('erro')->nullable();
            $table->timestamps();

            // Webhook da Meta repete evento: o mesmo comentário nunca entra duas vezes.
            $table->unique('comment_id');
            $table->index(['doctor_id', 'status', 'created_at']);
            $table->index(['intent', 'revisado_em']);
            $table->index('ig_id');
        });

        // Exemplos aprovados: o few-shot que volta para o prompt da agente.
        Schema::create('ia_examples', function (Blueprint $table) {
            $table->id();
            $table->foreignId('doctor_id')->constrained()->cascadeOnDelete();
            $table->string('intent', 30);
            $table->string('canal', 20)->nullable();
            $table->text('pergunta');
            $table->text('resposta');                    // o que o humano aprovou
            $table->text('resposta_rejeitada')->nullable(); // o rascunho ruim (contra-exemplo)
            $table->foreignId('approval_id')->nullable()->constrained('ia_approvals')->nullOnDelete();
            $table->integer('prioridade')->default(0);
            $table->boolean('ativo')->default(true);
            $table->foreignId('criado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['doctor_id', 'intent', 'ativo', 'prioridade']);
        });

        // Regras inegociáveis: o painel exibe, nunca deixa editar.
        Schema::create('ia_guardrails', function (Blueprint $table) {
            $table->id();
            $table->foreignId('doctor_id')->nullable()->constrained()->cascadeOnDelete(); // null = vale para as duas agentes
            $table->unsignedInteger('ordem')->default(0);
            $table->text('regra');
            $table->boolean('ativo')->default(true);
            $table->timestamps();

            $table->index(['doctor_id', 'ativo', 'ordem']);
        });

        // Configuração do portão, por agente.
        Schema::create('ia_gate_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('doctor_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('modo', 30)->default('treinamento');
            $table->decimal('limiar_acuracia', 4, 3)->default(0.900);
            $table->decimal('kill_switch_acuracia', 4, 3)->default(0.850);
            $table->unsignedInteger('min_amostras')->default(30);
            $table->unsignedInteger('janela')->default(50);
            $table->json('intents_sempre_revisa')->nullable();
            $table->unsignedInteger('timeout_min')->default(30);
            $table->boolean('dm_espera_ativa')->default(true);
            $table->text('dm_espera_texto')->nullable();
            $table->string('modelo', 60)->default('gpt-4.1');   // read-only no painel
            $table->json('notif_emails')->nullable();
            $table->boolean('notif_push')->default(true);
            $table->foreignId('atualizado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // Base de conhecimento (os cards que hoje estão no Supabase).
        Schema::create('ia_cards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('doctor_id')->constrained()->cascadeOnDelete();
            $table->text('pergunta');
            $table->text('resposta');
            $table->json('tags')->nullable();
            $table->string('status', 20)->default('validado');   // validado | pendente
            $table->string('origem', 40)->default('base_inicial'); // base_inicial | painel_aprovacao | manual
            $table->foreignId('approval_id')->nullable()->constrained('ia_approvals')->nullOnDelete();
            $table->boolean('ativo')->default(true);
            $table->foreignId('criado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['doctor_id', 'ativo', 'status']);
        });

        // Gatilhos de comentário (hoje luna_gatilhos).
        Schema::create('ia_triggers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('doctor_id')->constrained()->cascadeOnDelete();
            $table->string('termo', 190);
            $table->string('tipo', 30)->default('palavra_chave');
            $table->unsignedInteger('ordem')->default(0);
            $table->boolean('ativo')->default(true);
            $table->timestamps();

            $table->index(['doctor_id', 'ativo', 'ordem']);
        });

        // Push no celular (FCM) — quem recebe aviso de pendência.
        Schema::create('ia_push_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('token', 255);
            $table->string('device', 120)->nullable();
            $table->boolean('ativo')->default(true);
            $table->timestamps();

            $table->unique('token');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ia_push_devices');
        Schema::dropIfExists('ia_triggers');
        Schema::dropIfExists('ia_cards');
        Schema::dropIfExists('ia_gate_settings');
        Schema::dropIfExists('ia_guardrails');
        Schema::dropIfExists('ia_examples');
        Schema::dropIfExists('ia_approvals');
        Schema::dropIfExists('ia_prompt_versions');
    }
};
