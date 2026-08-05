<?php

namespace App\Models;

use App\Models\Concerns\PertenceAClinica;
use Database\Factories\SalaFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Sala extends Model
{
    use HasFactory;
    use PertenceAClinica;
    use SoftDeletes;

    protected $table = 'salas';

    protected $fillable = [
        'clinica_id',
        'nome',
        'numero',
        'descricao',
        'capacidade_atendimentos_simultaneos',
        'ativo',
    ];

    protected $casts = [
        'ativo' => 'boolean',
    ];

    protected static function newFactory(): SalaFactory
    {
        return SalaFactory::new();
    }

    public function profissionais(): BelongsToMany
    {
        return $this->belongsToMany(Profissional::class, 'profissional_sala')
            ->withPivot(['id', 'clinica_id', 'ativo'])
            ->withTimestamps();
    }

    public function agendamentos(): HasMany
    {
        return $this->hasMany(Agendamento::class, 'sala_id');
    }
}
