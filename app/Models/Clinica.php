<?php

namespace App\Models;

use Database\Factories\ClinicaFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Clinica extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'clinicas';

    protected $fillable = [
        'nome',
        'cnpj',
        'telefone',
        'email',
        'slug',
        'ativo',
    ];

    protected $casts = [
        'ativo' => 'boolean',
    ];

    protected static function newFactory(): ClinicaFactory
    {
        return ClinicaFactory::new();
    }

    public function usuarios(): HasMany
    {
        return $this->hasMany(Usuario::class, 'clinica_id');
    }
}
