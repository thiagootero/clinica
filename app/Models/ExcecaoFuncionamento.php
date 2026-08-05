<?php

namespace App\Models;

use App\Models\Concerns\PertenceAClinica;
use Database\Factories\ExcecaoFuncionamentoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ExcecaoFuncionamento extends Model
{
    use HasFactory;
    use PertenceAClinica;
    use SoftDeletes;

    protected $table = 'excecoes_funcionamento';

    protected $fillable = [
        'clinica_id',
        'data',
        'fechado',
        'abre_em',
        'fecha_em',
        'descricao',
    ];

    protected $casts = [
        'data' => 'date',
        'fechado' => 'boolean',
    ];

    protected static function newFactory(): ExcecaoFuncionamentoFactory
    {
        return ExcecaoFuncionamentoFactory::new();
    }

    public function clinica(): BelongsTo
    {
        return $this->belongsTo(Clinica::class, 'clinica_id');
    }
}
