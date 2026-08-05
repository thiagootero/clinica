<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('profissionais', function (Blueprint $table) {
            $table->boolean('oferece_retorno')->default(false)->after('duracao_padrao_atendimento');
            $table->unsignedSmallInteger('duracao_retorno_minutos')->nullable()->after('oferece_retorno');
            $table->unsignedSmallInteger('intervalo_retorno_dias')->nullable()->after('duracao_retorno_minutos');
        });
    }

    public function down(): void
    {
        Schema::table('profissionais', function (Blueprint $table) {
            $table->dropColumn(['oferece_retorno', 'duracao_retorno_minutos', 'intervalo_retorno_dias']);
        });
    }
};
