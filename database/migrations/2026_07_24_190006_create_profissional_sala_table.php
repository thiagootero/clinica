<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('profissional_sala', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinica_id')->constrained('clinicas');
            $table->foreignId('profissional_id')->constrained('profissionais');
            $table->foreignId('sala_id')->constrained('salas');
            $table->boolean('ativo')->default(true);
            $table->timestamps();
            $table->unique(['clinica_id', 'profissional_id', 'sala_id'], 'prof_sala_clinica_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('profissional_sala');
    }
};
