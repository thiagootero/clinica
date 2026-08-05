<?php

namespace Database\Factories;

use App\Enums\SituacaoAgendamento;
use App\Models\Agendamento;
use App\Models\Clinica;
use App\Models\Especialidade;
use App\Models\Paciente;
use App\Models\Profissional;
use App\Models\Sala;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Agendamento>
 */
class AgendamentoFactory extends Factory
{
    protected $model = Agendamento::class;

    public function definition(): array
    {
        $inicio = fake()->dateTimeBetween('+1 day 08:00', '+10 days 17:00');
        $fim = (clone $inicio)->modify('+30 minutes');

        return [
            'clinica_id' => Clinica::factory(),
            'paciente_id' => Paciente::factory(),
            'profissional_id' => Profissional::factory(),
            'especialidade_id' => Especialidade::factory(),
            'sala_id' => Sala::factory(),
            'data_hora_inicio' => $inicio,
            'data_hora_fim' => $fim,
            'duracao_minutos' => 30,
            'situacao' => SituacaoAgendamento::Agendado,
            'criado_por' => Usuario::factory(),
        ];
    }
}
