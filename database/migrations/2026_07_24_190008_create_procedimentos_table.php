<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('procedimentos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinica_id')->constrained('clinicas');
            $table->foreignId('especialidade_id')->constrained('especialidades');
            $table->string('nome');
            $table->string('codigo')->nullable();
            $table->text('descricao')->nullable();
            $table->unsignedSmallInteger('duracao_estimada_minutos')->nullable();
            $table->decimal('valor', 10, 2)->nullable();
            $table->boolean('ativo')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['clinica_id', 'especialidade_id', 'nome'], 'proc_clin_esp_nome_uq');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('procedimentos');
    }
};
