<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Os cards da Luna no Supabase (luna_cards) têm mais estrutura do que
 * pergunta/resposta: código estável, módulo, categoria, várias perguntas
 * equivalentes, resposta curta e resposta detalhada com regra de uso.
 * O painel passa a guardar tudo isso para a base ser revisada por inteiro
 * e para o n8n receber os cards no mesmo formato que já usa hoje.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ia_cards', function (Blueprint $table) {
            $table->string('codigo', 100)->nullable()->after('doctor_id');
            $table->string('modulo', 80)->nullable()->after('codigo');
            $table->string('categoria', 150)->nullable()->after('modulo');
            $table->json('perguntas_equivalentes')->nullable()->after('pergunta');
            $table->text('resposta_detalhada')->nullable()->after('resposta');

            $table->unique(['doctor_id', 'codigo']);
        });
    }

    public function down(): void
    {
        Schema::table('ia_cards', function (Blueprint $table) {
            $table->dropUnique(['doctor_id', 'codigo']);
            $table->dropColumn(['codigo', 'modulo', 'categoria', 'perguntas_equivalentes', 'resposta_detalhada']);
        });
    }
};
