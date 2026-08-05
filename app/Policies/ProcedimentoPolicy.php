<?php

namespace App\Policies;

use App\Models\Procedimento;
use App\Models\Usuario;

class ProcedimentoPolicy extends PoliticaBase
{
    public function viewAny(Usuario $usuario): bool
    {
        return $this->podeOperar($usuario);
    }

    public function view(Usuario $usuario, Procedimento $model): bool
    {
        return $this->podeOperar($usuario) && $this->mesmaClinica($usuario, $model);
    }

    public function create(Usuario $usuario): bool
    {
        return $this->podeOperar($usuario);
    }

    public function update(Usuario $usuario, Procedimento $model): bool
    {
        return $this->podeOperar($usuario) && $this->mesmaClinica($usuario, $model);
    }

    public function delete(Usuario $usuario, Procedimento $model): bool
    {
        return $this->podeOperar($usuario) && $this->mesmaClinica($usuario, $model);
    }
}
