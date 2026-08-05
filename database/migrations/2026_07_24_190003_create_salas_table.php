<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('salas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinica_id')->constrained('clinicas');
            $table->string('nome');
            $table->string('numero')->nullable();
            $table->text('descricao')->nullable();
            $table->unsignedSmallInteger('capacidade_atendimentos_simultaneos')->default(1);
            $table->boolean('ativo')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['clinica_id', 'nome'], 'sala_clinica_nome_uq');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('salas');
    }
};
