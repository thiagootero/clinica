<?php

namespace Database\Factories;

use App\Enums\DiaSemana;
use App\Models\Clinica;
use App\Models\HorarioFuncionamento;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HorarioFuncionamento>
 */
class HorarioFuncionamentoFactory extends Factory
{
    protected $model = HorarioFuncionamento::class;

    public function definition(): array
    {
        return [
            'clinica_id' => Clinica::factory(),
            'dia_semana' => fake()->randomElement(DiaSemana::cases())->value,
            'fechado' => false,
            'abre_em' => '08:00',
            'fecha_em' => '18:00',
        ];
    }

    public function fechado(): static
    {
        return $this->state(fn () => ['fechado' => true, 'abre_em' => null, 'fecha_em' => null]);
    }
}
