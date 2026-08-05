<?php

namespace Database\Factories;

use App\Models\Clinica;
use App\Models\ExcecaoFuncionamento;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExcecaoFuncionamento>
 */
class ExcecaoFuncionamentoFactory extends Factory
{
    protected $model = ExcecaoFuncionamento::class;

    public function definition(): array
    {
        return [
            'clinica_id' => Clinica::factory(),
            'data' => fake()->dateTimeBetween('+1 day', '+60 days')->format('Y-m-d'),
            'fechado' => true,
            'abre_em' => null,
            'fecha_em' => null,
            'descricao' => fake()->randomElement(['Feriado nacional', 'Recesso', 'Manutenção']),
        ];
    }

    public function comHorarioReduzido(): static
    {
        return $this->state(fn () => ['fechado' => false, 'abre_em' => '08:00', 'fecha_em' => '12:00']);
    }
}
