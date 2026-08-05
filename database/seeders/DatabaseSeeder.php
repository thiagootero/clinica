<?php

namespace Database\Seeders;

use App\Models\Clinica;
use App\Models\DisponibilidadeProfissional;
use App\Models\Especialidade;
use App\Models\Paciente;
use App\Models\Procedimento;
use App\Models\Profissional;
use App\Models\Sala;
use App\Models\Usuario;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $clinica = Clinica::factory()->create([
            'nome' => 'Clínica Central',
            'slug' => 'clinica-central',
            'email' => 'contato@clinicacentral.test',
        ]);

        Usuario::factory()->administrador()->create([
            'nome' => 'Administrador',
            'email' => 'admin@clinica.test',
        ]);

        $gerente = Usuario::factory()->gerente()->create([
            'clinica_id' => $clinica->id,
            'nome' => 'Gerente',
            'email' => 'gerente@clinica.test',
        ]);

        $especialidades = Especialidade::factory()
            ->count(4)
            ->create(['clinica_id' => $clinica->id]);

        $salas = Sala::factory()
            ->count(4)
            ->create(['clinica_id' => $clinica->id]);

        $profissionais = Profissional::factory()
            ->count(5)
            ->create(['clinica_id' => $clinica->id]);

        foreach ($profissionais as $profissional) {
            $especialidadesSorteadas = $especialidades->random(rand(1, min(2, $especialidades->count())));
            foreach ($especialidadesSorteadas as $especialidade) {
                $profissional->especialidades()->attach($especialidade->id, [
                    'clinica_id' => $clinica->id,
                    'duracao_atendimento_minutos' => $especialidade->duracao_padrao_minutos,
                    'ativo' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $salasSorteadas = $salas->random(rand(1, min(2, $salas->count())));
            foreach ($salasSorteadas as $sala) {
                $profissional->salas()->attach($sala->id, [
                    'clinica_id' => $clinica->id,
                    'ativo' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            DisponibilidadeProfissional::factory()->count(3)->create([
                'clinica_id' => $clinica->id,
                'profissional_id' => $profissional->id,
                'especialidade_id' => $especialidadesSorteadas->first()->id,
                'sala_id' => $salasSorteadas->first()->id,
                'criado_por' => $gerente->id,
            ]);
        }

        Paciente::factory()->count(20)->create([
            'clinica_id' => $clinica->id,
            'criado_por' => $gerente->id,
        ]);

        foreach ($especialidades as $especialidade) {
            Procedimento::factory()->count(3)->create([
                'clinica_id' => $clinica->id,
                'especialidade_id' => $especialidade->id,
            ]);
        }
    }
}
