<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('historicos_situacoes_agendamentos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinica_id')->constrained('clinicas');
            $table->foreignId('agendamento_id')->constrained('agendamentos');
            $table->string('situacao_anterior', 20)->nullable();
            $table->string('situacao_nova', 20);
            $table->text('motivo')->nullable();
            $table->text('observacoes')->nullable();
            $table->foreignId('alterado_por')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['clinica_id', 'agendamento_id'], 'hist_sit_clinica_agendamento_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('historicos_situacoes_agendamentos');
    }
};
