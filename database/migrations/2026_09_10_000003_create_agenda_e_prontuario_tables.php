<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->foreignId('doctor_id')->constrained();
            $table->foreignId('service_id')->nullable()->constrained()->nullOnDelete();
            $table->string('tipo', 20); // consulta, retorno, online, procedimento, cirurgia
            $table->dateTime('inicio');
            $table->dateTime('fim');
            $table->string('status', 20)->default('agendado'); // agendado, confirmado, realizado, faltou, cancelado, remarcado
            $table->string('sala')->nullable();
            $table->text('observacoes')->nullable();
            $table->unsignedBigInteger('kommo_lead_id')->nullable();
            $table->boolean('demo')->default(false);
            $table->timestamps();

            $table->index(['doctor_id', 'inicio']);
        });

        Schema::create('medical_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->foreignId('doctor_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('appointment_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete(); // autor
            $table->string('tipo', 20); // anamnese, evolucao, prescricao, exame, atestado, foto
            $table->string('titulo');
            $table->text('conteudo')->nullable(); // cifrado
            $table->text('dados')->nullable();    // cifrado (array estruturado da anamnese)
            $table->json('anexos')->nullable();
            $table->timestamp('assinado_em')->nullable();
            $table->boolean('demo')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('medical_records');
        Schema::dropIfExists('appointments');
    }
};
