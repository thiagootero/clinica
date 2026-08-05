<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('especialidades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinica_id')->constrained('clinicas');
            $table->string('nome');
            $table->text('descricao')->nullable();
            $table->unsignedSmallInteger('duracao_padrao_minutos')->default(30);
            $table->boolean('ativo')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['clinica_id', 'nome'], 'esp_clinica_nome_uq');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('especialidades');
    }
};
