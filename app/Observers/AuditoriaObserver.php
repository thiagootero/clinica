<?php

namespace App\Observers;

use App\Models\Auditoria;
use App\Models\Usuario;
use App\Support\ContextoAuditoria;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class AuditoriaObserver
{
    public function created(Model $model): void
    {
        $this->registrar('criado', $model, null, $model->getAttributes());
    }

    public function updated(Model $model): void
    {
        $alteracoes = $model->getChanges();

        if ($alteracoes === []) {
            return;
        }

        $anteriores = array_intersect_key($model->getOriginal(), $alteracoes);

        $this->registrar('atualizado', $model, $anteriores, $alteracoes);
    }

    public function deleted(Model $model): void
    {
        $this->registrar('excluido', $model, $model->getOriginal(), null);
    }

    protected function registrar(string $acao, Model $model, ?array $valoresAnteriores, ?array $valoresNovos): void
    {
        if ($model instanceof Auditoria) {
            return;
        }

        /** @var Usuario|null $usuario */
        $usuario = Auth::user();

        Auditoria::query()->create([
            'clinica_id' => $model->getAttribute('clinica_id') ?? $usuario?->clinica_id,
            'usuario_id' => $usuario?->id,
            'acao' => $acao,
            'modelo' => $model::class,
            'modelo_id' => $model->getKey(),
            'valores_anteriores' => $valoresAnteriores,
            'valores_novos' => $valoresNovos,
            'justificativa' => ContextoAuditoria::$justificativa,
            'endereco_ip' => Request::ip(),
            'created_at' => now(),
        ]);
    }
}
