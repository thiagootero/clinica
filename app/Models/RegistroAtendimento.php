<?php

namespace App\Models;

use App\Models\Concerns\PertenceAClinica;
use Database\Factories\RegistroAtendimentoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RegistroAtendimento extends Model
{
    use HasFactory;
    use PertenceAClinica;

    protected $table = 'registros_atendimentos';

    protected $fillable = [
        'clinica_id',
        'agendamento_id',
        'data_hora_realizacao',
        'resumo_atendimento',
        'observacoes_internas',
        'registrado_por',
    ];

    protected $casts = [
        'data_hora_realizacao' => 'datetime',
    ];

    protected static function newFactory(): RegistroAtendimentoFactory
    {
        return RegistroAtendimentoFactory::new();
    }

    public function agendamento(): BelongsTo
    {
        return $this->belongsTo(Agendamento::class, 'agendamento_id');
    }

    public function registrador(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'registrado_por');
    }
}
