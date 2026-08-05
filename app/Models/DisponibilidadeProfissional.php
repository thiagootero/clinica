<?php

namespace App\Models;

use App\Enums\SituacaoDisponibilidade;
use App\Models\Concerns\PertenceAClinica;
use Database\Factories\DisponibilidadeProfissionalFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class DisponibilidadeProfissional extends Model
{
    use HasFactory;
    use PertenceAClinica;
    use SoftDeletes;

    protected $table = 'disponibilidades_profissionais';

    protected $fillable = [
        'clinica_id',
        'profissional_id',
        'especialidade_id',
        'sala_id',
        'data_disponibilidade',
        'horario_inicio',
        'horario_fim',
        'duracao_atendimento_minutos',
        'intervalo_inicio',
        'intervalo_fim',
        'observacoes',
        'situacao',
        'criado_por',
    ];

    protected $casts = [
        'data_disponibilidade' => 'date',
        'situacao' => SituacaoDisponibilidade::class,
    ];

    protected static function newFactory(): DisponibilidadeProfissionalFactory
    {
        return DisponibilidadeProfissionalFactory::new();
    }

    public function profissional(): BelongsTo
    {
        return $this->belongsTo(Profissional::class, 'profissional_id');
    }

    public function especialidade(): BelongsTo
    {
        return $this->belongsTo(Especialidade::class, 'especialidade_id');
    }

    public function sala(): BelongsTo
    {
        return $this->belongsTo(Sala::class, 'sala_id');
    }
}
