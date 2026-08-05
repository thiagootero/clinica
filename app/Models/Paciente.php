<?php

namespace App\Models;

use App\Enums\SexoPaciente;
use App\Models\Concerns\PertenceAClinica;
use Database\Factories\PacienteFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Paciente extends Model
{
    use HasFactory;
    use PertenceAClinica;
    use SoftDeletes;

    protected $table = 'pacientes';

    protected $fillable = [
        'clinica_id',
        'nome',
        'nome_social',
        'cpf',
        'cartao_sus',
        'data_nascimento',
        'sexo',
        'telefone',
        'telefone_secundario',
        'email',
        'cep',
        'logradouro',
        'numero',
        'complemento',
        'bairro',
        'cidade',
        'estado',
        'nome_responsavel',
        'telefone_responsavel',
        'observacoes',
        'ativo',
        'criado_por',
    ];

    protected $casts = [
        'data_nascimento' => 'date',
        'sexo' => SexoPaciente::class,
        'ativo' => 'boolean',
    ];

    protected static function newFactory(): PacienteFactory
    {
        return PacienteFactory::new();
    }

    public function criador(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'criado_por');
    }

    public function agendamentos(): HasMany
    {
        return $this->hasMany(Agendamento::class, 'paciente_id');
    }
}
