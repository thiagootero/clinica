<?php

namespace App\Models\Concerns;

use App\Models\Clinica;
use App\Models\Scopes\EscopoClinica;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

trait PertenceAClinica
{
    public static function bootPertenceAClinica(): void
    {
        static::addGlobalScope(new EscopoClinica);

        static::creating(function ($model): void {
            if (filled($model->clinica_id)) {
                return;
            }

            /** @var Usuario|null $usuario */
            $usuario = Auth::user();
            $clinicaId = $usuario?->clinicaAtivaId();

            if ($clinicaId) {
                $model->clinica_id = $clinicaId;
            }
        });
    }

    public function clinica(): BelongsTo
    {
        return $this->belongsTo(Clinica::class, 'clinica_id');
    }

    public function scopeDaClinica(Builder $query, int|string|null $clinicaId = null): Builder
    {
        /** @var Usuario|null $usuario */
        $usuario = Auth::user();
        $clinicaId ??= $usuario?->clinicaAtivaId();

        return $query->when($clinicaId, fn (Builder $builder) => $builder->where('clinica_id', $clinicaId));
    }
}
