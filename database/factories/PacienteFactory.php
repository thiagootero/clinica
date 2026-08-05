<?php

namespace Database\Factories;

use App\Models\Clinica;
use App\Models\Paciente;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Paciente>
 */
class PacienteFactory extends Factory
{
    protected $model = Paciente::class;

    public function definition(): array
    {
        return [
            'clinica_id' => Clinica::factory(),
            'nome' => fake()->name(),
            'cpf' => fake()->unique()->numerify('###.###.###-##'),
            'data_nascimento' => fake()->date(),
            'telefone' => fake()->phoneNumber(),
            'email' => fake()->safeEmail(),
            'cidade' => fake()->city(),
            'estado' => fake()->stateAbbr(),
            'ativo' => true,
            'criado_por' => Usuario::factory(),
        ];
    }
}
