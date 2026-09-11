<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('automation_rules', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 10)->nullable()->unique(); // A1…A7 do catálogo
            $table->string('nome');
            $table->text('descricao')->nullable();
            $table->json('gatilho');
            $table->json('condicoes')->nullable();
            $table->json('acoes');
            $table->json('pipeline_ids');
            $table->string('modo', 20)->default('so_registrar'); // so_registrar, ativo, desligado
            $table->unsignedSmallInteger('ordem')->default(0);
            $table->boolean('demo')->default(false);
            $table->timestamps();
        });

        Schema::create('automation_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('automation_rule_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('kommo_lead_id');
            $table->string('lead_nome')->nullable();
            $table->unsignedBigInteger('pipeline_id')->nullable();
            $table->json('evento')->nullable();
            $table->json('acoes')->nullable();
            $table->string('status', 20); // registrado, executado, ignorado, falhou
            $table->string('motivo')->nullable();
            $table->boolean('demo')->default(false);
            $table->timestamps();

            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('automation_runs');
        Schema::dropIfExists('automation_rules');
    }
};
