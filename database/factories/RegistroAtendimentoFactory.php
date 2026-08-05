<?php

namespace Database\Factories;

use App\Models\Agendamento;
use App\Models\Clinica;
use App\Models\RegistroAtendimento;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RegistroAtendimento>
 */
class RegistroAtendimentoFactory extends Factory
{
    protected $model = RegistroAtendimento::class;

    public function definition(): array
    {
        return [
            'clinica_id' => Clinica::factory(),
            'agendamento_id' => Agendamento::factory(),
            'data_hora_realizacao' => now(),
            'resumo_atendimento' => fake()->paragraph(),
            'observacoes_internas' => fake()->sentence(),
            'registrado_por' => Usuario::factory(),
        ];
    }
}
