<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kommo_api_calls', function (Blueprint $table) {
            $table->id();
            $table->string('endpoint');
            $table->unsignedSmallInteger('status');
            $table->unsignedInteger('duracao_ms');
            $table->unsignedInteger('itens')->default(0);
            $table->dateTime('created_at');

            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kommo_api_calls');
    }
};
