<?php

namespace Database\Factories;

use App\Enums\SituacaoDisponibilidade;
use App\Models\Clinica;
use App\Models\DisponibilidadeProfissional;
use App\Models\Especialidade;
use App\Models\Profissional;
use App\Models\Sala;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DisponibilidadeProfissional>
 */
class DisponibilidadeProfissionalFactory extends Factory
{
    protected $model = DisponibilidadeProfissional::class;

    public function definition(): array
    {
        return [
            'clinica_id' => Clinica::factory(),
            'profissional_id' => Profissional::factory(),
            'especialidade_id' => Especialidade::factory(),
            'sala_id' => Sala::factory(),
            'data_disponibilidade' => fake()->dateTimeBetween('+1 day', '+20 days')->format('Y-m-d'),
            'horario_inicio' => '08:00',
            'horario_fim' => '12:00',
            'duracao_atendimento_minutos' => 30,
            'intervalo_inicio' => '10:00',
            'intervalo_fim' => '10:20',
            'situacao' => SituacaoDisponibilidade::Ativa,
            'criado_por' => Usuario::factory(),
        ];
    }
}
