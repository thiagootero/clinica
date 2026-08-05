<?php

namespace Tests\Feature;

use App\Models\Clinica;
use App\Models\Paciente;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PainelAdminSmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_gerente_acessa_todas_as_paginas_do_painel(): void
    {
        $clinica = Clinica::factory()->create();
        $gerente = Usuario::factory()->gerente()->create(['clinica_id' => $clinica->id]);
        $paciente = Paciente::factory()->create(['clinica_id' => $clinica->id, 'criado_por' => $gerente->id]);

        $this->actingAs($gerente);

        $rotas = [
            '/admin',
            '/admin/agendar-consulta',
            '/admin/comparativo-salas',
            '/admin/especialidades',
            '/admin/pacientes',
            "/admin/pacientes/{$paciente->id}/historico",
            '/admin/pacientes-nao-confirmados',
            '/admin/profissionais',
            '/admin/salas',
            '/admin/usuarios',
        ];

        foreach ($rotas as $rota) {
            $this->get($rota)->assertSuccessful();
        }

        // Gestão de clínicas é exclusiva do Administrador.
        $this->get('/admin/clinicas')->assertForbidden();
    }

    public function test_administrador_sem_clinica_ativa_nao_opera_mas_gerencia_clinicas(): void
    {
        $administrador = Usuario::factory()->administrador()->create();

        $this->actingAs($administrador);

        $this->get('/admin')->assertSuccessful();
        $this->get('/admin/clinicas')->assertSuccessful();
        $this->get('/admin/selecionar-clinica')->assertSuccessful();
        $this->get('/admin/agendar-consulta')->assertForbidden();
    }

    public function test_administrador_opera_a_clinica_selecionada(): void
    {
        $clinica = Clinica::factory()->create();
        $administrador = Usuario::factory()->administrador()->create();

        $this->actingAs($administrador);
        $this->withSession([Usuario::SESSAO_CLINICA_ATIVA => $clinica->id]);

        $this->get('/admin/agendar-consulta')->assertSuccessful();
        $this->get('/admin/pacientes')->assertSuccessful();
    }
}
