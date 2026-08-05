<?php

namespace App\Models;

use App\Models\Concerns\PertenceAClinica;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HistoricoSituacaoAgendamento extends Model
{
    use PertenceAClinica;

    public $timestamps = false;

    protected $table = 'historicos_situacoes_agendamentos';

    protected $fillable = [
        'clinica_id',
        'agendamento_id',
        'situacao_anterior',
        'situacao_nova',
        'motivo',
        'observacoes',
        'alterado_por',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function agendamento(): BelongsTo
    {
        return $this->belongsTo(Agendamento::class, 'agendamento_id');
    }

    public function alteradoPor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'alterado_por');
    }
}
