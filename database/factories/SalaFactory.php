<?php

namespace Database\Factories;

use App\Models\Clinica;
use App\Models\Sala;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Sala>
 */
class SalaFactory extends Factory
{
    protected $model = Sala::class;

    public function definition(): array
    {
        return [
            'clinica_id' => Clinica::factory(),
            'nome' => 'Sala '.fake()->unique()->numberBetween(1, 20),
            'numero' => (string) fake()->numberBetween(1, 20),
            'descricao' => fake()->sentence(),
            'capacidade_atendimentos_simultaneos' => fake()->randomElement([1, 1, 1, 2]),
            'ativo' => true,
        ];
    }
}
