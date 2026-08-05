<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

class EscopoClinica implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $usuario = Auth::user();

        if (! $usuario) {
            return;
        }

        $clinicaId = $usuario->clinicaAtivaId();

        if ($clinicaId) {
            $builder->where($model->getTable().'.clinica_id', $clinicaId);

            return;
        }

        // Administrador autenticado sem clínica ativa selecionada: não mistura
        // dados de clínicas diferentes, apenas não mostra nada até ele escolher.
        $builder->whereRaw('1 = 0');
    }
}
