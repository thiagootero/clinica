<?php

namespace App\Observers;

use App\Models\Clinica;
use App\Models\HorarioFuncionamento;

class ClinicaObserver
{
    public function created(Clinica $clinica): void
    {
        HorarioFuncionamento::criarPadraoParaClinica($clinica);
    }
}
