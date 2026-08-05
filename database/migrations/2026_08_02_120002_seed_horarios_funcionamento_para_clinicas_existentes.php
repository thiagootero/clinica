<?php

use App\Models\Clinica;
use App\Models\HorarioFuncionamento;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Clinica::query()->each(function (Clinica $clinica): void {
            HorarioFuncionamento::criarPadraoParaClinica($clinica);
        });
    }

    public function down(): void
    {
        HorarioFuncionamento::query()->delete();
    }
};
