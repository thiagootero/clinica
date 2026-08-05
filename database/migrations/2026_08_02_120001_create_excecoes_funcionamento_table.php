<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('excecoes_funcionamento', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinica_id')->constrained('clinicas');
            $table->date('data');
            $table->boolean('fechado')->default(true);
            $table->time('abre_em')->nullable();
            $table->time('fecha_em')->nullable();
            $table->string('descricao')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['clinica_id', 'data'], 'excecoes_func_clinica_data_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('excecoes_funcionamento');
    }
};
