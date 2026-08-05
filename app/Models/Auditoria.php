<?php

namespace App\Models;

use App\Models\Concerns\PertenceAClinica;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Auditoria extends Model
{
    use PertenceAClinica;

    public $timestamps = false;

    protected $table = 'auditorias';

    protected $fillable = [
        'clinica_id',
        'usuario_id',
        'acao',
        'modelo',
        'modelo_id',
        'valores_anteriores',
        'valores_novos',
        'justificativa',
        'endereco_ip',
        'created_at',
    ];

    protected $casts = [
        'valores_anteriores' => 'array',
        'valores_novos' => 'array',
        'created_at' => 'datetime',
    ];

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }
}
