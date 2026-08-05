<?php

namespace App\Policies;

use App\Models\DisponibilidadeProfissional;
use App\Models\Usuario;

class DisponibilidadeProfissionalPolicy extends PoliticaBase
{
    public function viewAny(Usuario $usuario): bool
    {
        return $this->podeOperar($usuario);
    }

    public function view(Usuario $usuario, DisponibilidadeProfissional $model): bool
    {
        return $this->podeOperar($usuario) && $this->mesmaClinica($usuario, $model);
    }

    public function create(Usuario $usuario): bool
    {
        return $this->podeOperar($usuario);
    }

    public function update(Usuario $usuario, DisponibilidadeProfissional $model): bool
    {
        return $this->podeOperar($usuario) && $this->mesmaClinica($usuario, $model);
    }

    public function delete(Usuario $usuario, DisponibilidadeProfissional $model): bool
    {
        return $this->podeOperar($usuario) && $this->mesmaClinica($usuario, $model);
    }
}
