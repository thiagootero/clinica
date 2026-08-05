<?php

namespace App\Policies;

use App\Enums\PerfilUsuario;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\Model;

class PoliticaBase
{
    protected function ehAdministrador(Usuario $usuario): bool
    {
        return $usuario->perfil === PerfilUsuario::Administrador;
    }

    protected function mesmaClinica(Usuario $usuario, ?Model $model = null): bool
    {
        if (! $model || ! isset($model->clinica_id)) {
            return true;
        }

        return (int) $usuario->clinicaAtivaId() === (int) $model->clinica_id;
    }

    /**
     * Administrador e Gerente têm o mesmo nível de acesso operacional. A única
     * exigência é existir uma clínica ativa: o Gerente sempre tem (a sua
     * própria); o Administrador precisa selecionar uma antes de operar.
     */
    protected function podeOperar(Usuario $usuario): bool
    {
        return $usuario->clinicaAtivaId() !== null;
    }
}
