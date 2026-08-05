<?php

namespace App\Policies;

use App\Models\Agendamento;
use App\Models\Usuario;

class AgendamentoPolicy extends PoliticaBase
{
    public function viewAny(Usuario $usuario): bool
    {
        return $this->podeOperar($usuario);
    }

    public function view(Usuario $usuario, Agendamento $model): bool
    {
        return $this->podeOperar($usuario) && $this->mesmaClinica($usuario, $model);
    }

    public function create(Usuario $usuario): bool
    {
        return $this->podeOperar($usuario);
    }

    public function update(Usuario $usuario, Agendamento $model): bool
    {
        return $this->podeOperar($usuario) && $this->mesmaClinica($usuario, $model);
    }

    public function delete(Usuario $usuario, Agendamento $model): bool
    {
        return false;
    }
}
