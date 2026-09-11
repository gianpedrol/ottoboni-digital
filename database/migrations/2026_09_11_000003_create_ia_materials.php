<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Materiais que a agente pode mandar junto com a resposta: a imagem dos
 * programas, o e-book, o vídeo da Dra. O prompt já pedia "[ENVIAR A IMAGEM
 * DOS PROGRAMAS]" sem ter de onde tirar a imagem — agora tem.
 *
 * Imagem vai como anexo no Direct; documento, vídeo e link vão como
 * mensagem com o endereço (o Instagram não aceita PDF como anexo).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ia_materials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('doctor_id')->constrained()->cascadeOnDelete();
            $table->string('codigo', 60);                 // como a agente se refere: "programas"
            $table->string('nome', 120);
            $table->string('tipo', 20)->default('imagem'); // imagem | documento | video | link
            $table->string('arquivo', 255)->nullable();    // no disco "public"
            $table->string('url', 1024)->nullable();       // quando é link externo
            $table->string('token', 40)->unique();         // parte da URL pública do arquivo
            $table->text('quando_usar')->nullable();       // instrução para a agente
            $table->boolean('ativo')->default(true);
            $table->unsignedSmallInteger('ordem')->default(0);
            $table->foreignId('criado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['doctor_id', 'codigo']);
        });

        Schema::table('ia_approvals', function (Blueprint $table) {
            $table->json('materiais')->nullable()->after('cards_usados');       // o que a agente quis mandar
            $table->json('final_materiais')->nullable()->after('materiais');   // o que o humano deixou
        });

        // Cada card pode indicar o que vai junto quando ele é usado: a pergunta
        // sobre o Flor&Ser Raiz manda o PDF do Raiz, não um material genérico.
        Schema::table('ia_cards', function (Blueprint $table) {
            $table->json('materiais')->nullable()->after('resposta_detalhada');
        });
    }

    public function down(): void
    {
        Schema::table('ia_cards', function (Blueprint $table) {
            $table->dropColumn('materiais');
        });

        Schema::table('ia_approvals', function (Blueprint $table) {
            $table->dropColumn(['materiais', 'final_materiais']);
        });

        Schema::dropIfExists('ia_materials');
    }
};
