<?php

namespace Database\Factories;

use App\Models\Clinica;
use App\Models\Especialidade;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Especialidade>
 */
class EspecialidadeFactory extends Factory
{
    protected $model = Especialidade::class;

    public function definition(): array
    {
        return [
            'clinica_id' => Clinica::factory(),
            'nome' => fake()->unique()->randomElement([
                'Clínica médica',
                'Psicologia',
                'Odontologia',
                'Pediatria',
                'Ultrassonografia',
                'Fisioterapia',
            ]).' '.fake()->unique()->numberBetween(1, 99),
            'descricao' => fake()->sentence(),
            'duracao_padrao_minutos' => fake()->randomElement([20, 30, 40, 50, 60]),
            'ativo' => true,
        ];
    }
}
