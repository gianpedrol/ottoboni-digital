<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Trilha de auditoria: quem exportou (com qual filtro), quem abriu
        // ficha, quem criou/ativou régua. Dado de saúde exige isso (LGPD).
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('acao', 50);
            $table->json('contexto')->nullable();
            $table->dateTime('created_at');

            $table->index(['acao', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
