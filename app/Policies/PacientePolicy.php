<?php

namespace App\Policies;

use App\Models\Paciente;
use App\Models\Usuario;

class PacientePolicy extends PoliticaBase
{
    public function viewAny(Usuario $usuario): bool
    {
        return $this->podeOperar($usuario);
    }

    public function view(Usuario $usuario, Paciente $paciente): bool
    {
        return $this->podeOperar($usuario) && $this->mesmaClinica($usuario, $paciente);
    }

    public function create(Usuario $usuario): bool
    {
        return $this->podeOperar($usuario);
    }

    public function update(Usuario $usuario, Paciente $paciente): bool
    {
        return $this->podeOperar($usuario) && $this->mesmaClinica($usuario, $paciente);
    }

    public function delete(Usuario $usuario, Paciente $paciente): bool
    {
        return $this->podeOperar($usuario) && $this->mesmaClinica($usuario, $paciente);
    }
}
