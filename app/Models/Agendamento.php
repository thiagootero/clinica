<?php

namespace App\Models;

use App\Enums\FormaConfirmacao;
use App\Enums\SituacaoAgendamento;
use App\Enums\SolicitanteCancelamento;
use App\Models\Concerns\PertenceAClinica;
use Database\Factories\AgendamentoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Agendamento extends Model
{
    use HasFactory;
    use PertenceAClinica;
    use SoftDeletes;

    protected $table = 'agendamentos';

    protected $fillable = [
        'clinica_id',
        'paciente_id',
        'profissional_id',
        'especialidade_id',
        'sala_id',
        'data_hora_inicio',
        'data_hora_fim',
        'duracao_minutos',
        'situacao',
        'tipo_atendimento',
        'observacoes_agendamento',
        'descricao',
        'agendamento_anterior_id',
        'criado_por',
        'confirmado_por',
        'confirmado_em',
        'forma_confirmacao',
        'observacoes_confirmacao',
        'cancelado_por',
        'cancelado_em',
        'motivo_cancelamento',
        'solicitante_cancelamento',
        'remarcado_por',
        'remarcado_em',
        'motivo_remarcacao',
        'realizado_por',
        'realizado_em',
    ];

    protected $casts = [
        'data_hora_inicio' => 'datetime',
        'data_hora_fim' => 'datetime',
        'confirmado_em' => 'datetime',
        'cancelado_em' => 'datetime',
        'remarcado_em' => 'datetime',
        'realizado_em' => 'datetime',
        'situacao' => SituacaoAgendamento::class,
        'forma_confirmacao' => FormaConfirmacao::class,
        'solicitante_cancelamento' => SolicitanteCancelamento::class,
    ];

    protected static function newFactory(): AgendamentoFactory
    {
        return AgendamentoFactory::new();
    }

    public function paciente(): BelongsTo
    {
        return $this->belongsTo(Paciente::class, 'paciente_id');
    }

    public function profissional(): BelongsTo
    {
        return $this->belongsTo(Profissional::class, 'profissional_id');
    }

    public function especialidade(): BelongsTo
    {
        return $this->belongsTo(Especialidade::class, 'especialidade_id');
    }

    public function procedimentosPrevistos(): BelongsToMany
    {
        return $this->belongsToMany(Procedimento::class, 'agendamento_procedimento_previsto')
            ->withPivot(['id', 'clinica_id'])
            ->withTimestamps();
    }

    public function sala(): BelongsTo
    {
        return $this->belongsTo(Sala::class, 'sala_id');
    }

    public function agendamentoAnterior(): BelongsTo
    {
        return $this->belongsTo(self::class, 'agendamento_anterior_id');
    }

    public function remarcacoes(): HasMany
    {
        return $this->hasMany(self::class, 'agendamento_anterior_id');
    }

    public function procedimentos(): BelongsToMany
    {
        return $this->belongsToMany(Procedimento::class, 'agendamento_procedimento')
            ->withPivot(['id', 'clinica_id', 'quantidade', 'observacoes', 'registrado_por'])
            ->withTimestamps();
    }

    public function registroAtendimento(): HasOne
    {
        return $this->hasOne(RegistroAtendimento::class, 'agendamento_id');
    }

    public function historicosSituacoes(): HasMany
    {
        return $this->hasMany(HistoricoSituacaoAgendamento::class, 'agendamento_id');
    }
}
