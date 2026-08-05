<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('profissionais', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinica_id')->constrained('clinicas');
            $table->string('nome');
            $table->string('cpf', 14)->nullable();
            $table->string('tipo_registro_profissional')->nullable();
            $table->string('numero_registro_profissional')->nullable();
            $table->string('telefone')->nullable();
            $table->string('email')->nullable();
            $table->unsignedSmallInteger('duracao_padrao_atendimento')->nullable();
            $table->text('observacoes')->nullable();
            $table->boolean('ativo')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['clinica_id', 'cpf'], 'prof_clinica_cpf_uq');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('profissionais');
    }
};
