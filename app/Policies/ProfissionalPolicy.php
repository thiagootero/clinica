<?php

namespace App\Policies;

use App\Models\Profissional;
use App\Models\Usuario;

class ProfissionalPolicy extends PoliticaBase
{
    public function viewAny(Usuario $usuario): bool
    {
        return $this->podeOperar($usuario);
    }

    public function view(Usuario $usuario, Profissional $model): bool
    {
        return $this->podeOperar($usuario) && $this->mesmaClinica($usuario, $model);
    }

    public function create(Usuario $usuario): bool
    {
        return $this->podeOperar($usuario);
    }

    public function update(Usuario $usuario, Profissional $model): bool
    {
        return $this->podeOperar($usuario) && $this->mesmaClinica($usuario, $model);
    }

    public function delete(Usuario $usuario, Profissional $model): bool
    {
        return $this->podeOperar($usuario) && $this->mesmaClinica($usuario, $model);
    }
}
