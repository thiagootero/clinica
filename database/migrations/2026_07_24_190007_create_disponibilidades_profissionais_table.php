<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('disponibilidades_profissionais', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinica_id')->constrained('clinicas');
            $table->foreignId('profissional_id')->constrained('profissionais');
            $table->foreignId('especialidade_id')->nullable()->constrained('especialidades')->nullOnDelete();
            $table->foreignId('sala_id')->nullable()->constrained('salas')->nullOnDelete();
            $table->date('data_disponibilidade');
            $table->time('horario_inicio');
            $table->time('horario_fim');
            $table->unsignedSmallInteger('duracao_atendimento_minutos')->nullable();
            $table->time('intervalo_inicio')->nullable();
            $table->time('intervalo_fim')->nullable();
            $table->text('observacoes')->nullable();
            $table->string('situacao', 20);
            $table->foreignId('criado_por')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['clinica_id', 'profissional_id', 'data_disponibilidade'], 'disp_prof_clinica_prof_data_idx');
            $table->index(['clinica_id', 'data_disponibilidade'], 'disp_prof_clinica_data_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('disponibilidades_profissionais');
    }
};
