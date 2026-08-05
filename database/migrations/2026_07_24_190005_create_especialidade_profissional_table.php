<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('especialidade_profissional', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinica_id')->constrained('clinicas');
            $table->foreignId('especialidade_id')->constrained('especialidades');
            $table->foreignId('profissional_id')->constrained('profissionais');
            $table->unsignedSmallInteger('duracao_atendimento_minutos')->nullable();
            $table->boolean('ativo')->default(true);
            $table->timestamps();
            $table->unique(['clinica_id', 'especialidade_id', 'profissional_id'], 'esp_prof_clinica_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('especialidade_profissional');
    }
};
