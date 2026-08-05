<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('horarios_funcionamento', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinica_id')->constrained('clinicas');
            $table->unsignedTinyInteger('dia_semana');
            $table->boolean('fechado')->default(false);
            $table->time('abre_em')->nullable();
            $table->time('fecha_em')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['clinica_id', 'dia_semana'], 'horarios_func_clinica_dia_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('horarios_funcionamento');
    }
};
