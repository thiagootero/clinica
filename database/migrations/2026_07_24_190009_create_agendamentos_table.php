<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agendamentos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinica_id')->constrained('clinicas');
            $table->foreignId('paciente_id')->constrained('pacientes');
            $table->foreignId('profissional_id')->constrained('profissionais');
            $table->foreignId('especialidade_id')->constrained('especialidades');
            $table->foreignId('sala_id')->constrained('salas');
            $table->dateTime('data_hora_inicio');
            $table->dateTime('data_hora_fim');
            $table->unsignedSmallInteger('duracao_minutos');
            $table->string('situacao', 20);
            $table->string('tipo_atendimento')->nullable();
            $table->text('observacoes_agendamento')->nullable();
            $table->foreignId('agendamento_anterior_id')->nullable()->constrained('agendamentos')->nullOnDelete();
            $table->foreignId('criado_por')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->foreignId('confirmado_por')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->dateTime('confirmado_em')->nullable();
            $table->string('forma_confirmacao')->nullable();
            $table->text('observacoes_confirmacao')->nullable();
            $table->foreignId('cancelado_por')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->dateTime('cancelado_em')->nullable();
            $table->text('motivo_cancelamento')->nullable();
            $table->string('solicitante_cancelamento')->nullable();
            $table->foreignId('remarcado_por')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->dateTime('remarcado_em')->nullable();
            $table->text('motivo_remarcacao')->nullable();
            $table->foreignId('realizado_por')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->dateTime('realizado_em')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['clinica_id', 'profissional_id', 'data_hora_inicio'], 'ag_prof_data_idx');
            $table->index(['clinica_id', 'sala_id', 'data_hora_inicio'], 'ag_sala_data_idx');
            $table->index(['clinica_id', 'paciente_id', 'data_hora_inicio'], 'ag_pac_data_idx');
            $table->index(['clinica_id', 'situacao'], 'ag_clin_sit_idx');
            $table->index(['clinica_id', 'especialidade_id'], 'ag_clin_esp_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agendamentos');
    }
};
