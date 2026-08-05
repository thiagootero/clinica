<?php

namespace Database\Factories;

use App\Models\Clinica;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Clinica>
 */
class ClinicaFactory extends Factory
{
    protected $model = Clinica::class;

    public function definition(): array
    {
        $nome = fake()->unique()->company();

        return [
            'nome' => $nome,
            'cnpj' => fake()->numerify('##.###.###/####-##'),
            'telefone' => fake()->phoneNumber(),
            'email' => fake()->companyEmail(),
            'slug' => Str::slug($nome.'-'.fake()->unique()->numberBetween(1, 999)),
            'ativo' => true,
        ];
    }
}
