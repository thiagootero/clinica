<?php

namespace App\Models;

use App\Models\Concerns\PertenceAClinica;
use Database\Factories\EspecialidadeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Especialidade extends Model
{
    use HasFactory;
    use PertenceAClinica;
    use SoftDeletes;

    protected $table = 'especialidades';

    protected $fillable = [
        'clinica_id',
        'nome',
        'descricao',
        'duracao_padrao_minutos',
        'ativo',
    ];

    protected $casts = [
        'ativo' => 'boolean',
    ];

    protected static function newFactory(): EspecialidadeFactory
    {
        return EspecialidadeFactory::new();
    }

    public function profissionais(): BelongsToMany
    {
        return $this->belongsToMany(Profissional::class, 'especialidade_profissional')
            ->withPivot(['id', 'clinica_id', 'duracao_atendimento_minutos', 'ativo'])
            ->withTimestamps();
    }

    public function procedimentos(): HasMany
    {
        return $this->hasMany(Procedimento::class, 'especialidade_id');
    }

    public function agendamentos(): HasMany
    {
        return $this->hasMany(Agendamento::class, 'especialidade_id');
    }
}
