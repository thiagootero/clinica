<?php

namespace App\Models;

use App\Enums\DiaSemana;
use App\Models\Concerns\PertenceAClinica;
use Database\Factories\HorarioFuncionamentoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class HorarioFuncionamento extends Model
{
    use HasFactory;
    use PertenceAClinica;
    use SoftDeletes;

    protected $table = 'horarios_funcionamento';

    protected $fillable = [
        'clinica_id',
        'dia_semana',
        'fechado',
        'abre_em',
        'fecha_em',
    ];

    protected $casts = [
        'dia_semana' => DiaSemana::class,
        'fechado' => 'boolean',
    ];

    protected static function newFactory(): HorarioFuncionamentoFactory
    {
        return HorarioFuncionamentoFactory::new();
    }

    public function clinica(): BelongsTo
    {
        return $this->belongsTo(Clinica::class, 'clinica_id');
    }

    /**
     * Horário padrão de uma clínica nova: segunda a sexta 08:00–18:00, fim de semana fechado.
     *
     * @return array<int, array{dia_semana: int, fechado: bool, abre_em: ?string, fecha_em: ?string}>
     */
    public static function padraoSemanal(): array
    {
        return collect(DiaSemana::cases())
            ->map(function (DiaSemana $dia): array {
                $fimDeSemana = in_array($dia, [DiaSemana::Sabado, DiaSemana::Domingo], true);

                return [
                    'dia_semana' => $dia->value,
                    'fechado' => $fimDeSemana,
                    'abre_em' => $fimDeSemana ? null : '08:00',
                    'fecha_em' => $fimDeSemana ? null : '18:00',
                ];
            })
            ->all();
    }

    /**
     * Garante que a clínica tem as 7 linhas de horário semanal, criando as que faltarem com o
     * padrão. Idempotente — seguro chamar tanto na criação da clínica quanto ao abrir a tela de
     * configuração de uma clínica mais antiga que ainda não tinha esse recurso.
     */
    public static function criarPadraoParaClinica(Clinica $clinica): void
    {
        foreach (static::padraoSemanal() as $linha) {
            static::query()->withoutGlobalScopes()->firstOrCreate(
                ['clinica_id' => $clinica->id, 'dia_semana' => $linha['dia_semana']],
                $linha,
            );
        }
    }
}
