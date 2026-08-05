<?php

namespace Tests\Feature\Agendamentos;

use App\Enums\FormaConfirmacao;
use App\Models\Agendamento;
use App\Models\Clinica;
use App\Models\Especialidade;
use App\Models\HorarioFuncionamento;
use App\Models\Paciente;
use App\Models\Procedimento;
use App\Models\Profissional;
use App\Models\Sala;
use App\Models\Usuario;
use App\Services\ServicoCancelamentoAgendamento;
use App\Services\ServicoConfirmacaoAgendamento;
use App\Services\ServicoCorrecaoAgendamento;
use App\Services\ServicoCriacaoAgendamento;
use App\Services\ServicoEdicaoAgendamento;
use App\Services\ServicoFinalizacaoAtendimento;
use App\Services\ServicoValidacaoConflito;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ServicosAgendamentoTest extends TestCase
{
    use RefreshDatabase;

    protected Clinica $clinica;

    protected Usuario $usuario;

    protected Especialidade $especialidade;

    protected Profissional $profissional;

    protected Sala $sala;

    protected Sala $segundaSala;

    protected Paciente $paciente;

    protected ServicoCriacaoAgendamento $servicoCriacaoAgendamento;

    protected function setUp(): void
    {
        parent::setUp();

        $this->clinica = Clinica::factory()->create();
        $this->usuario = Usuario::factory()->gerente()->create([
            'clinica_id' => $this->clinica->id,
        ]);
        $this->actingAs($this->usuario);

        $this->especialidade = Especialidade::factory()->create([
            'clinica_id' => $this->clinica->id,
            'duracao_padrao_minutos' => 30,
        ]);
        $this->profissional = Profissional::factory()->create(['clinica_id' => $this->clinica->id]);
        $this->sala = Sala::factory()->create(['clinica_id' => $this->clinica->id, 'capacidade_atendimentos_simultaneos' => 1]);
        $this->segundaSala = Sala::factory()->create(['clinica_id' => $this->clinica->id, 'capacidade_atendimentos_simultaneos' => 2]);
        $this->paciente = Paciente::factory()->create(['clinica_id' => $this->clinica->id, 'criado_por' => $this->usuario->id]);

        $this->profissional->especialidades()->attach($this->especialidade->id, [
            'clinica_id' => $this->clinica->id,
            'duracao_atendimento_minutos' => 30,
            'ativo' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->profissional->salas()->attach($this->sala->id, [
            'clinica_id' => $this->clinica->id,
            'ativo' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->profissional->salas()->attach($this->segundaSala->id, [
            'clinica_id' => $this->clinica->id,
            'ativo' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->profissional->disponibilidades()->create([
            'clinica_id' => $this->clinica->id,
            'especialidade_id' => $this->especialidade->id,
            'sala_id' => null,
            'data_disponibilidade' => Carbon::tomorrow()->toDateString(),
            'horario_inicio' => '08:00',
            'horario_fim' => '12:00',
            'duracao_atendimento_minutos' => 30,
            'intervalo_inicio' => '10:00',
            'intervalo_fim' => '10:20',
            'situacao' => 'ativa',
            'criado_por' => $this->usuario->id,
        ]);

        $this->servicoCriacaoAgendamento = $this->app->make(ServicoCriacaoAgendamento::class);
    }

    public function test_cria_agendamento_sem_conflito(): void
    {
        $agendamento = $this->servicoCriacaoAgendamento->executar($this->dadosPadrao(), $this->usuario);

        $this->assertDatabaseHas('agendamentos', [
            'id' => $agendamento->id,
            'clinica_id' => $this->clinica->id,
            'situacao' => 'agendado',
        ]);
    }

    public function test_detecta_conflito_de_profissional(): void
    {
        $this->servicoCriacaoAgendamento->executar($this->dadosPadrao(), $this->usuario);

        $this->expectException(ValidationException::class);

        $this->servicoCriacaoAgendamento->executar($this->dadosPadrao(), $this->usuario);
    }

    public function test_detecta_conflito_de_sala(): void
    {
        $outroProfissional = Profissional::factory()->create(['clinica_id' => $this->clinica->id]);
        $outroProfissional->especialidades()->attach($this->especialidade->id, [
            'clinica_id' => $this->clinica->id,
            'ativo' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $outroProfissional->salas()->attach($this->sala->id, [
            'clinica_id' => $this->clinica->id,
            'ativo' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $outroProfissional->disponibilidades()->create([
            'clinica_id' => $this->clinica->id,
            'especialidade_id' => $this->especialidade->id,
            'data_disponibilidade' => Carbon::tomorrow()->toDateString(),
            'horario_inicio' => '08:00',
            'horario_fim' => '12:00',
            'duracao_atendimento_minutos' => 30,
            'situacao' => 'ativa',
            'criado_por' => $this->usuario->id,
        ]);

        $this->servicoCriacaoAgendamento->executar($this->dadosPadrao(), $this->usuario);

        $this->expectException(ValidationException::class);

        $this->servicoCriacaoAgendamento->executar([
            ...$this->dadosPadrao(),
            'profissional_id' => $outroProfissional->id,
        ], $this->usuario);
    }

    public function test_respeita_capacidade_da_sala(): void
    {
        $profissional2 = Profissional::factory()->create(['clinica_id' => $this->clinica->id]);
        $profissional2->especialidades()->attach($this->especialidade->id, [
            'clinica_id' => $this->clinica->id,
            'ativo' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $profissional2->salas()->attach($this->segundaSala->id, [
            'clinica_id' => $this->clinica->id,
            'ativo' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $profissional2->disponibilidades()->create([
            'clinica_id' => $this->clinica->id,
            'especialidade_id' => $this->especialidade->id,
            'data_disponibilidade' => Carbon::tomorrow()->toDateString(),
            'horario_inicio' => '08:00',
            'horario_fim' => '12:00',
            'duracao_atendimento_minutos' => 30,
            'situacao' => 'ativa',
            'criado_por' => $this->usuario->id,
        ]);

        $paciente2 = Paciente::factory()->create(['clinica_id' => $this->clinica->id, 'criado_por' => $this->usuario->id]);
        $paciente3 = Paciente::factory()->create(['clinica_id' => $this->clinica->id, 'criado_por' => $this->usuario->id]);

        $this->servicoCriacaoAgendamento->executar([
            ...$this->dadosPadrao(),
            'sala_id' => $this->segundaSala->id,
        ], $this->usuario);

        $this->servicoCriacaoAgendamento->executar([
            ...$this->dadosPadrao(),
            'profissional_id' => $profissional2->id,
            'paciente_id' => $paciente2->id,
            'sala_id' => $this->segundaSala->id,
        ], $this->usuario);

        $this->expectException(ValidationException::class);

        $this->app->make(ServicoValidacaoConflito::class)->validar(
            $paciente3,
            $profissional2,
            $this->segundaSala,
            Carbon::tomorrow()->setTime(8, 0),
            Carbon::tomorrow()->setTime(8, 30),
            $this->especialidade->id,
        );
    }

    public function test_edicao_atualiza_o_mesmo_registro_e_mantem_situacao_confirmado(): void
    {
        $agendamento = $this->servicoCriacaoAgendamento->executar($this->dadosPadrao(), $this->usuario);

        $this->app->make(ServicoConfirmacaoAgendamento::class)->executar($agendamento, FormaConfirmacao::Presencial, $this->usuario);

        $editado = $this->app->make(ServicoEdicaoAgendamento::class)->executar($agendamento->refresh(), [
            'data_hora_inicio' => Carbon::tomorrow()->setTime(9, 0)->toDateTimeString(),
            'duracao_minutos' => 30,
            'sala_id' => $this->sala->id,
        ], $this->usuario);

        $this->assertSame($agendamento->id, $editado->id);
        $this->assertSame(1, Agendamento::query()->where('paciente_id', $this->paciente->id)->count());
        $this->assertDatabaseHas('agendamentos', [
            'id' => $agendamento->id,
            'situacao' => 'confirmado',
            'data_hora_inicio' => Carbon::tomorrow()->setTime(9, 0)->toDateTimeString(),
        ]);
    }

    public function test_edicao_permite_trocar_paciente_procedimentos_e_descricao(): void
    {
        $agendamento = $this->servicoCriacaoAgendamento->executar($this->dadosPadrao(), $this->usuario);
        $outroPaciente = Paciente::factory()->create(['clinica_id' => $this->clinica->id, 'criado_por' => $this->usuario->id]);
        $procedimento = Procedimento::factory()->create([
            'clinica_id' => $this->clinica->id,
            'especialidade_id' => $this->especialidade->id,
        ]);

        $editado = $this->app->make(ServicoEdicaoAgendamento::class)->executar($agendamento, [
            'paciente_id' => $outroPaciente->id,
            'descricao' => 'Paciente relatou dor lombar.',
            'procedimentos_previstos_ids' => [$procedimento->id],
        ], $this->usuario);

        $this->assertSame($outroPaciente->id, $editado->paciente_id);
        $this->assertSame('Paciente relatou dor lombar.', $editado->descricao);
        $this->assertTrue($editado->procedimentosPrevistos->pluck('id')->contains($procedimento->id));
    }

    public function test_edicao_nao_bloqueia_conflito_consigo_mesmo(): void
    {
        $agendamento = $this->servicoCriacaoAgendamento->executar($this->dadosPadrao(), $this->usuario);

        $editado = $this->app->make(ServicoEdicaoAgendamento::class)->executar($agendamento, [
            'data_hora_inicio' => $agendamento->data_hora_inicio->toDateTimeString(),
            'duracao_minutos' => 30,
            'sala_id' => $this->sala->id,
        ], $this->usuario);

        $this->assertSame($agendamento->id, $editado->id);
    }

    public function test_edicao_fora_da_disponibilidade_sem_confirmacao_e_bloqueada(): void
    {
        $agendamento = $this->servicoCriacaoAgendamento->executar($this->dadosPadrao(), $this->usuario);

        $this->expectException(ValidationException::class);

        $this->app->make(ServicoEdicaoAgendamento::class)->executar($agendamento, [
            'data_hora_inicio' => Carbon::tomorrow()->setTime(15, 0)->toDateTimeString(),
            'duracao_minutos' => 30,
            'sala_id' => $this->sala->id,
        ], $this->usuario);
    }

    public function test_edicao_fora_da_disponibilidade_com_confirmacao_cria_disponibilidade_extra(): void
    {
        HorarioFuncionamento::query()->withoutGlobalScopes()->updateOrCreate(
            ['clinica_id' => $this->clinica->id, 'dia_semana' => Carbon::tomorrow()->isoWeekday()],
            ['fechado' => false, 'abre_em' => '00:00:00', 'fecha_em' => '23:59:00'],
        );

        $agendamento = $this->servicoCriacaoAgendamento->executar($this->dadosPadrao(), $this->usuario);
        $novoInicio = Carbon::tomorrow()->setTime(15, 0);

        $editado = $this->app->make(ServicoEdicaoAgendamento::class)->executar($agendamento, [
            'data_hora_inicio' => $novoInicio->toDateTimeString(),
            'duracao_minutos' => 30,
            'sala_id' => $this->sala->id,
            'confirmar_disponibilidade_extra' => true,
        ], $this->usuario);

        $this->assertTrue($novoInicio->equalTo($editado->data_hora_inicio));
        $this->assertDatabaseHas('disponibilidades_profissionais', [
            'profissional_id' => $this->profissional->id,
            'sala_id' => $this->sala->id,
            'horario_inicio' => '15:00:00',
        ]);
    }

    public function test_edicao_de_horario_registra_linha_do_tempo(): void
    {
        $agendamento = $this->servicoCriacaoAgendamento->executar($this->dadosPadrao(), $this->usuario);

        $this->app->make(ServicoEdicaoAgendamento::class)->executar($agendamento, [
            'data_hora_inicio' => Carbon::tomorrow()->setTime(9, 0)->toDateTimeString(),
            'duracao_minutos' => 30,
            'sala_id' => $this->sala->id,
            'motivo' => 'Paciente pediu para adiantar.',
        ], $this->usuario);

        $this->assertDatabaseHas('historicos_situacoes_agendamentos', [
            'agendamento_id' => $agendamento->id,
            'situacao_anterior' => 'agendado',
            'situacao_nova' => 'agendado',
        ]);
    }

    public function test_cancelamento_registra_motivo(): void
    {
        $agendamento = $this->servicoCriacaoAgendamento->executar($this->dadosPadrao(), $this->usuario);

        $this->app->make(ServicoCancelamentoAgendamento::class)->executar(
            $agendamento,
            'Paciente sem condições de comparecer',
            'paciente',
            $this->usuario,
        );

        $this->assertDatabaseHas('agendamentos', [
            'id' => $agendamento->id,
            'situacao' => 'cancelado',
            'solicitante_cancelamento' => 'paciente',
        ]);
    }

    public function test_finalizacao_registra_procedimentos(): void
    {
        $agendamento = $this->servicoCriacaoAgendamento->executar($this->dadosPadrao(), $this->usuario);
        $procedimento = Procedimento::factory()->create([
            'clinica_id' => $this->clinica->id,
            'especialidade_id' => $this->especialidade->id,
        ]);

        $this->app->make(ServicoConfirmacaoAgendamento::class)->executar(
            $agendamento,
            FormaConfirmacao::Presencial,
            $this->usuario,
        );

        $this->app->make(ServicoFinalizacaoAtendimento::class)->executar($agendamento, [
            'resumo_atendimento' => 'Atendimento realizado com sucesso.',
            'procedimentos' => [
                [
                    'procedimento_id' => $procedimento->id,
                    'quantidade' => 2,
                ],
            ],
        ], $this->usuario);

        $this->assertDatabaseHas('registros_atendimentos', ['agendamento_id' => $agendamento->id]);
        $this->assertDatabaseHas('agendamento_procedimento', [
            'agendamento_id' => $agendamento->id,
            'procedimento_id' => $procedimento->id,
            'quantidade' => 2,
        ]);
    }

    public function test_bloqueia_horario_fora_da_disponibilidade(): void
    {
        $this->expectException(ValidationException::class);

        $this->servicoCriacaoAgendamento->executar([
            ...$this->dadosPadrao(),
            'data_hora_inicio' => Carbon::tomorrow()->setTime(13, 0)->toDateTimeString(),
        ], $this->usuario);
    }

    public function test_bloqueia_horario_durante_intervalo(): void
    {
        $this->expectException(ValidationException::class);

        $this->servicoCriacaoAgendamento->executar([
            ...$this->dadosPadrao(),
            'data_hora_inicio' => Carbon::tomorrow()->setTime(10, 0)->toDateTimeString(),
        ], $this->usuario);
    }

    public function test_mesmo_horario_na_segunda_tentativa_e_bloqueado(): void
    {
        $outroGerente = Usuario::factory()->gerente()->create([
            'clinica_id' => $this->clinica->id,
        ]);

        $this->servicoCriacaoAgendamento->executar($this->dadosPadrao(), $this->usuario);

        $this->expectException(ValidationException::class);

        $this->servicoCriacaoAgendamento->executar($this->dadosPadrao(), $outroGerente);
    }

    public function test_confirmacao_agendamento(): void
    {
        $agendamento = $this->servicoCriacaoAgendamento->executar($this->dadosPadrao(), $this->usuario);

        $confirmado = $this->app->make(ServicoConfirmacaoAgendamento::class)->executar(
            $agendamento,
            FormaConfirmacao::WhatsApp,
            $this->usuario,
            'Paciente confirmou por WhatsApp.',
        );

        $this->assertSame('confirmado', $confirmado->situacao->value);
        $this->assertSame($this->usuario->id, $confirmado->confirmado_por);
        $this->assertNotNull($confirmado->confirmado_em);
        $this->assertDatabaseHas('agendamentos', [
            'id' => $agendamento->id,
            'situacao' => 'confirmado',
            'forma_confirmacao' => 'whatsapp',
        ]);
    }

    public function test_nao_permite_confirmar_agendamento_que_nao_esta_agendado(): void
    {
        $agendamento = $this->servicoCriacaoAgendamento->executar($this->dadosPadrao(), $this->usuario);
        $this->app->make(ServicoConfirmacaoAgendamento::class)->executar($agendamento, FormaConfirmacao::Telefone, $this->usuario);

        $this->expectException(ValidationException::class);

        $this->app->make(ServicoConfirmacaoAgendamento::class)->executar($agendamento, FormaConfirmacao::Telefone, $this->usuario);
    }

    public function test_nao_permite_finalizar_atendimento_sem_confirmacao_previa(): void
    {
        $agendamento = $this->servicoCriacaoAgendamento->executar($this->dadosPadrao(), $this->usuario);

        $this->expectException(ValidationException::class);

        $this->app->make(ServicoFinalizacaoAtendimento::class)->executar($agendamento, [
            'resumo_atendimento' => 'Tentativa sem confirmação.',
        ], $this->usuario);
    }

    public function test_correcao_de_agendamento_exige_e_registra_justificativa(): void
    {
        $agendamento = $this->servicoCriacaoAgendamento->executar($this->dadosPadrao(), $this->usuario);

        $this->app->make(ServicoCorrecaoAgendamento::class)->executar(
            $agendamento,
            ['sala_id' => $this->segundaSala->id],
            'Sala original ficou indisponível por manutenção.',
            $this->usuario,
        );

        $this->assertDatabaseHas('agendamentos', [
            'id' => $agendamento->id,
            'sala_id' => $this->segundaSala->id,
        ]);
        $this->assertDatabaseHas('auditorias', [
            'modelo_id' => $agendamento->id,
            'acao' => 'atualizado',
            'justificativa' => 'Sala original ficou indisponível por manutenção.',
        ]);
    }

    public function test_administrador_precisa_selecionar_clinica_ativa_para_operar(): void
    {
        $administrador = Usuario::factory()->administrador()->create();

        $this->assertNull($administrador->clinicaAtivaId());
        $this->assertFalse($administrador->can('create', Paciente::class));
        $this->assertFalse($administrador->can('create', Agendamento::class));

        session([Usuario::SESSAO_CLINICA_ATIVA => $this->clinica->id]);

        $this->assertSame($this->clinica->id, $administrador->clinicaAtivaId());
        $this->assertTrue($administrador->can('create', Paciente::class));
        $this->assertTrue($administrador->can('create', Agendamento::class));
    }

    public function test_gerente_esta_sempre_preso_a_propria_clinica(): void
    {
        $this->assertSame($this->clinica->id, $this->usuario->clinicaAtivaId());
        $this->assertTrue($this->usuario->can('create', Paciente::class));
    }

    protected function dadosPadrao(): array
    {
        return [
            'paciente_id' => $this->paciente->id,
            'profissional_id' => $this->profissional->id,
            'especialidade_id' => $this->especialidade->id,
            'sala_id' => $this->sala->id,
            'data_hora_inicio' => Carbon::tomorrow()->setTime(8, 0)->toDateTimeString(),
            'duracao_minutos' => 30,
        ];
    }
}
