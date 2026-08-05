<?php

namespace Database\Factories;

use App\Models\Clinica;
use App\Models\Especialidade;
use App\Models\Procedimento;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Procedimento>
 */
class ProcedimentoFactory extends Factory
{
    protected $model = Procedimento::class;

    public function definition(): array
    {
        return [
            'clinica_id' => Clinica::factory(),
            'especialidade_id' => Especialidade::factory(),
            'nome' => fake()->unique()->words(2, true),
            'codigo' => fake()->optional()->bothify('PROC-###'),
            'descricao' => fake()->sentence(),
            'duracao_estimada_minutos' => fake()->randomElement([20, 30, 40, 60]),
            'valor' => fake()->randomFloat(2, 50, 500),
            'ativo' => true,
        ];
    }
}
