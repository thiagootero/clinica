<?php

namespace Database\Factories;

use App\Models\Clinica;
use App\Models\Profissional;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Profissional>
 */
class ProfissionalFactory extends Factory
{
    protected $model = Profissional::class;

    public function definition(): array
    {
        return [
            'clinica_id' => Clinica::factory(),
            'nome' => fake()->name(),
            'cpf' => fake()->unique()->numerify('###.###.###-##'),
            'tipo_registro_profissional' => fake()->randomElement(['CRM', 'CRO', 'CRP', 'CREFITO']),
            'numero_registro_profissional' => fake()->numerify('######'),
            'telefone' => fake()->phoneNumber(),
            'email' => fake()->safeEmail(),
            'duracao_padrao_atendimento' => fake()->randomElement([20, 30, 40, 60]),
            'observacoes' => fake()->sentence(),
            'ativo' => true,
        ];
    }
}
