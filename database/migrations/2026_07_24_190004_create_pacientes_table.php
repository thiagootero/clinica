<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pacientes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinica_id')->constrained('clinicas');
            $table->string('nome');
            $table->string('nome_social')->nullable();
            $table->string('cpf', 14)->nullable();
            $table->string('cartao_sus')->nullable();
            $table->date('data_nascimento');
            $table->string('sexo', 20)->nullable();
            $table->string('telefone');
            $table->string('telefone_secundario')->nullable();
            $table->string('email')->nullable();
            $table->string('cep')->nullable();
            $table->string('logradouro')->nullable();
            $table->string('numero')->nullable();
            $table->string('complemento')->nullable();
            $table->string('bairro')->nullable();
            $table->string('cidade')->nullable();
            $table->string('estado', 2)->nullable();
            $table->string('nome_responsavel')->nullable();
            $table->string('telefone_responsavel')->nullable();
            $table->text('observacoes')->nullable();
            $table->boolean('ativo')->default(true);
            $table->foreignId('criado_por')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['clinica_id', 'cpf'], 'pac_clinica_cpf_uq');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pacientes');
    }
};
