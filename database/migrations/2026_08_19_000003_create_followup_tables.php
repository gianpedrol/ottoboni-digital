<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('followup_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('doctor_id')->constrained()->cascadeOnDelete();
            $table->string('nome');
            $table->text('descricao')->nullable();
            $table->boolean('ativo')->default(false);
            $table->string('gatilho', 30);
            $table->json('gatilho_config')->nullable();
            $table->foreignId('criado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('followup_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_id')->constrained('followup_plans')->cascadeOnDelete();
            $table->unsignedInteger('ordem');
            $table->unsignedInteger('offset_horas');
            $table->string('canal', 20)->default('auto');
            $table->string('modo', 20)->default('texto_fixo');
            $table->text('texto')->nullable();
            $table->text('prompt_ia')->nullable();
            $table->unsignedBigInteger('kommo_bot_id')->nullable();
            $table->boolean('ativo')->default(true);
            $table->timestamps();
        });

        Schema::create('followup_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_id')->constrained('followup_plans')->cascadeOnDelete();
            $table->foreignId('step_id')->constrained('followup_steps')->cascadeOnDelete();
            $table->unsignedBigInteger('lead_id_kommo');
            $table->foreignId('doctor_id')->constrained();
            $table->string('status', 20)->default('agendado');
            $table->dateTime('agendado_para');
            $table->dateTime('executado_em')->nullable();
            $table->string('canal_usado', 20)->nullable();
            $table->string('motivo_cancelamento')->nullable();
            $table->timestamps();

            // Idempotência: reprocessar nunca manda duas vezes.
            $table->unique(['plan_id', 'step_id', 'lead_id_kommo']);
            $table->index(['status', 'agendado_para']);
            $table->index('lead_id_kommo');
        });

        Schema::create('followup_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('run_id')->constrained('followup_runs')->cascadeOnDelete();
            $table->string('canal', 20);
            $table->text('texto_final');
            $table->string('origem_texto', 20);
            $table->json('resposta_n8n')->nullable();
            $table->text('erro')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('followup_messages');
        Schema::dropIfExists('followup_runs');
        Schema::dropIfExists('followup_steps');
        Schema::dropIfExists('followup_plans');
    }
};
