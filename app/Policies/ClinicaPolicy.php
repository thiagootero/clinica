<?php

namespace App\Policies;

use App\Models\Clinica;
use App\Models\Usuario;

/**
 * Gestão de clínicas é exclusiva do Administrador do sistema: é ele quem
 * decide quais clínicas existem e qual delas está operando a cada momento.
 */
class ClinicaPolicy extends PoliticaBase
{
    public function viewAny(Usuario $usuario): bool
    {
        return $this->ehAdministrador($usuario);
    }

    public function view(Usuario $usuario, Clinica $clinica): bool
    {
        return $this->ehAdministrador($usuario);
    }

    public function create(Usuario $usuario): bool
    {
        return $this->ehAdministrador($usuario);
    }

    public function update(Usuario $usuario, Clinica $clinica): bool
    {
        return $this->ehAdministrador($usuario);
    }

    public function delete(Usuario $usuario, Clinica $clinica): bool
    {
        return $this->ehAdministrador($usuario);
    }
}
