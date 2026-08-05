<?php

namespace App\Models;

use App\Models\Concerns\PertenceAClinica;
use Database\Factories\ProfissionalFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Profissional extends Model
{
    use HasFactory;
    use PertenceAClinica;
    use SoftDeletes;

    protected $table = 'profissionais';

    protected $fillable = [
        'clinica_id',
        'nome',
        'cpf',
        'tipo_registro_profissional',
        'numero_registro_profissional',
        'telefone',
        'email',
        'duracao_padrao_atendimento',
        'oferece_retorno',
        'duracao_retorno_minutos',
        'intervalo_retorno_dias',
        'observacoes',
        'ativo',
    ];

    protected $casts = [
        'ativo' => 'boolean',
        'oferece_retorno' => 'boolean',
    ];

    protected static function newFactory(): ProfissionalFactory
    {
        return ProfissionalFactory::new();
    }

    public function especialidades(): BelongsToMany
    {
        return $this->belongsToMany(Especialidade::class, 'especialidade_profissional')
            ->withPivot(['id', 'clinica_id', 'duracao_atendimento_minutos', 'ativo'])
            ->withTimestamps();
    }

    public function salas(): BelongsToMany
    {
        return $this->belongsToMany(Sala::class, 'profissional_sala')
            ->withPivot(['id', 'clinica_id', 'ativo'])
            ->withTimestamps();
    }

    public function disponibilidades(): HasMany
    {
        return $this->hasMany(DisponibilidadeProfissional::class, 'profissional_id');
    }

    public function agendamentos(): HasMany
    {
        return $this->hasMany(Agendamento::class, 'profissional_id');
    }
}
