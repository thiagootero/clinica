<?php

namespace App\Models;

use App\Models\Concerns\PertenceAClinica;
use Database\Factories\ProcedimentoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Procedimento extends Model
{
    use HasFactory;
    use PertenceAClinica;
    use SoftDeletes;

    protected $table = 'procedimentos';

    protected $fillable = [
        'clinica_id',
        'especialidade_id',
        'nome',
        'codigo',
        'descricao',
        'duracao_estimada_minutos',
        'valor',
        'ativo',
    ];

    protected $casts = [
        'ativo' => 'boolean',
        'valor' => 'decimal:2',
    ];

    protected static function newFactory(): ProcedimentoFactory
    {
        return ProcedimentoFactory::new();
    }

    public function especialidade(): BelongsTo
    {
        return $this->belongsTo(Especialidade::class, 'especialidade_id');
    }

    public function agendamentos(): BelongsToMany
    {
        return $this->belongsToMany(Agendamento::class, 'agendamento_procedimento')
            ->withPivot(['id', 'clinica_id', 'quantidade', 'observacoes', 'registrado_por'])
            ->withTimestamps();
    }

    public function agendamentosPrevistos(): BelongsToMany
    {
        return $this->belongsToMany(Agendamento::class, 'agendamento_procedimento_previsto')
            ->withPivot(['id', 'clinica_id'])
            ->withTimestamps();
    }
}
