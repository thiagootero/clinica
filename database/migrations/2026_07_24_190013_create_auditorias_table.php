<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('auditorias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinica_id')->nullable()->constrained('clinicas')->nullOnDelete();
            $table->foreignId('usuario_id')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->string('acao');
            $table->string('modelo');
            $table->unsignedBigInteger('modelo_id');
            $table->json('valores_anteriores')->nullable();
            $table->json('valores_novos')->nullable();
            $table->text('justificativa')->nullable();
            $table->string('endereco_ip')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['modelo', 'modelo_id'], 'aud_modelo_registro_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auditorias');
    }
};
