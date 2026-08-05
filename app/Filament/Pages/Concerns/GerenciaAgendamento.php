<?php

namespace App\Filament\Pages\Concerns;

use App\Enums\FormaConfirmacao;
use App\Enums\SituacaoAgendamento;
use App\Filament\Support\ResumoAgendamentoSchema;
use App\Models\Agendamento;
use App\Models\Procedimento;
use App\Models\Profissional;
use App\Models\Sala;
use App\Services\ServicoCancelamentoAgendamento;
use App\Services\ServicoConfirmacaoAgendamento;
use App\Services\ServicoConflitoSalaPredefinida;
use App\Services\ServicoDisponibilidadeAgenda;
use App\Services\ServicoEdicaoAgendamento;
use App\Services\ServicoFinalizacaoAtendimento;
use App\Services\ServicoReversaoAtendimento;
use App\Services\ServicoReversaoConfirmacao;
use App\Support\DuracaoAtendimento;
use Carbon\Carbon;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Support\Collection;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\ValidationException;

trait GerenciaAgendamento
{
    use CamposConsulta;

    /**
     * @var array<string, Collection>
     */
    protected array $cacheSalasComOcupacao = [];

    public function resumoAgendamentoAction(): Action
    {
        return Action::make('resumoAgendamento')
            ->label('Ver resumo')
            ->icon('heroicon-o-eye')
            ->color('gray')
            ->modalHeading(function (array $arguments): string {
                $agendamento = Agendamento::query()->with('paciente')->find($arguments['agendamentoId']);

                return 'Resumo da consulta de '.($agendamento?->paciente?->nome ?? '');
            })
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Fechar')
            ->modalWidth('2xl')
            ->schema(function (array $arguments): array {
                $agendamento = Agendamento::query()
                    ->with(['paciente', 'profissional', 'especialidade', 'sala', 'procedimentos', 'procedimentosPrevistos', 'registroAtendimento'])
                    ->findOrFail($arguments['agendamentoId']);

                return ResumoAgendamentoSchema::schema($agendamento);
            });
    }

    public function ajustarAgendamentoAction(): Action
    {
        return Action::make('ajustarAgendamento')
            ->modalHeading(function (array $arguments): string {
                $agendamento = Agendamento::query()->with('paciente')->find($arguments['agendamentoId']);

                return 'Ajustar agendamento de '.($agendamento?->paciente?->nome ?? '');
            })
            ->modalSubmitActionLabel('Salvar')
            ->modalWidth('2xl')
            ->fillForm(function (array $arguments): array {
                $agendamento = Agendamento::query()
                    ->with(['registroAtendimento', 'procedimentos', 'procedimentosPrevistos'])
                    ->find($arguments['agendamentoId']);

                return [
                    'agendamentoId' => $arguments['agendamentoId'],
                    'acao' => $arguments['acao'] ?? null,
                    'paciente_id' => $agendamento?->paciente_id,
                    'especialidade_id' => $agendamento?->especialidade_id,
                    'tipo_atendimento' => $agendamento?->tipo_atendimento,
                    'data_edicao' => $agendamento?->data_hora_inicio?->toDateString(),
                    'hora_edicao' => $agendamento ? (int) $agendamento->data_hora_inicio->format('H') : null,
                    'minuto_edicao' => $agendamento ? (int) $agendamento->data_hora_inicio->format('i') : null,
                    'sala_id' => $agendamento?->sala_id,
                    'duracao_minutos' => $agendamento?->duracao_minutos,
                    'procedimentos_previstos_ids' => $agendamento?->procedimentosPrevistos->pluck('id')->all() ?? [],
                    'descricao' => $agendamento?->descricao,
                    'resumo_atendimento' => $agendamento?->registroAtendimento?->resumo_atendimento,
                    'procedimentos' => match (true) {
                        $agendamento?->procedimentos->isNotEmpty() => $agendamento->procedimentos->map(fn (Procedimento $procedimento): array => [
                            'procedimento_id' => $procedimento->id,
                            'quantidade' => $procedimento->pivot->quantidade,
                        ])->all(),
                        $agendamento?->procedimentosPrevistos->isNotEmpty() => $agendamento->procedimentosPrevistos->map(fn (Procedimento $procedimento): array => [
                            'procedimento_id' => $procedimento->id,
                            'quantidade' => 1,
                        ])->all(),
                        default => [],
                    },
                ];
            })
            ->schema([
                Hidden::make('agendamentoId'),

                Placeholder::make('descricaoInfo')
                    ->hiddenLabel()
                    ->content(fn (Get $get): ?string => Agendamento::query()->find($get('agendamentoId'))?->descricao)
                    ->visible(fn (Get $get): bool => $get('acao') !== 'editar'
                        && filled(Agendamento::query()->find($get('agendamentoId'))?->descricao)),

                Radio::make('acao')
                    ->label('O que deseja fazer?')
                    ->options(function (Get $get): array {
                        $agendamento = Agendamento::query()->find($get('agendamentoId'));

                        return match ($agendamento?->situacao) {
                            SituacaoAgendamento::Agendado => [
                                'confirmar' => 'Confirmar consulta',
                                'editar' => 'Editar consulta',
                                'cancelar' => 'Cancelar agendamento',
                            ],
                            SituacaoAgendamento::Confirmado => [
                                'editar' => 'Editar consulta',
                                'finalizar' => 'Finalizar atendimento',
                                'voltar_agendado' => 'Voltar para não confirmado',
                                'cancelar' => 'Cancelar agendamento',
                            ],
                            SituacaoAgendamento::Realizado => [
                                'editar_atendimento' => 'Editar resumo e procedimentos',
                                'reverter' => 'Reverter para confirmado',
                            ],
                            default => [],
                        };
                    })
                    ->live()
                    ->required(),

                Select::make('forma_confirmacao')
                    ->label('Forma de confirmação')
                    ->options(FormaConfirmacao::class)
                    ->visible(fn (Get $get): bool => $get('acao') === 'confirmar'),

                Textarea::make('observacoes_confirmacao')
                    ->label('Observações da confirmação')
                    ->visible(fn (Get $get): bool => $get('acao') === 'confirmar')
                    ->columnSpanFull(),

                $this->campoPaciente()
                    ->visible(fn (Get $get): bool => $get('acao') === 'editar'),

                Select::make('especialidade_id')
                    ->label('Especialidade')
                    ->live()
                    ->visible(function (Get $get): bool {
                        if ($get('acao') !== 'editar') {
                            return false;
                        }

                        $profissional = Agendamento::query()->find($get('agendamentoId'))?->profissional;

                        return ($profissional?->especialidades->count() ?? 0) > 1;
                    })
                    ->options(function (Get $get): array {
                        $profissional = Agendamento::query()->find($get('agendamentoId'))?->profissional;

                        return $profissional?->especialidades->pluck('nome', 'id')->all() ?? [];
                    })
                    ->required(fn (Get $get): bool => $get('acao') === 'editar'),

                Radio::make('tipo_atendimento')
                    ->label('Tipo de atendimento')
                    ->options(['consulta' => 'Consulta', 'retorno' => 'Retorno'])
                    ->inline()
                    ->visible(function (Get $get): bool {
                        if ($get('acao') !== 'editar') {
                            return false;
                        }

                        $profissional = Agendamento::query()->find($get('agendamentoId'))?->profissional;

                        return (bool) $profissional?->oferece_retorno;
                    }),

                ...$this->camposDataHoraEdicao(
                    fn (Get $get): bool => $get('acao') === 'editar',
                    fn (Get $get) => Agendamento::query()->find($get('agendamentoId'))?->clinica,
                ),

                Select::make('duracao_minutos')
                    ->label('Duração')
                    ->options(DuracaoAtendimento::options())
                    ->live()
                    ->visible(fn (Get $get): bool => $get('acao') === 'editar')
                    ->required(fn (Get $get): bool => $get('acao') === 'editar')
                    ->hint(function (Get $get): ?string {
                        $agendamento = Agendamento::query()->find($get('agendamentoId'));
                        $duracao = (int) ($get('duracao_minutos') ?: 0);
                        $inicio = $this->dataHoraEdicao($get);

                        if (! $agendamento || ! $duracao || ! $inicio) {
                            return null;
                        }

                        $fim = $inicio->copy()->addMinutes($duracao);

                        if (! $this->servicoDisponibilidadeAgenda()->estaDentroDaDisponibilidade($agendamento->profissional, $inicio, $fim)) {
                            return 'Fora da disponibilidade cadastrada do profissional nesse dia.';
                        }

                        $conflito = $this->conflitoDeHorario($agendamento->profissional, $inicio, $fim, $agendamento->id);

                        if (! $conflito) {
                            return null;
                        }

                        return 'Conflita com '.$conflito->paciente?->nome.' ('.$conflito->data_hora_inicio->format('H:i').' às '.$conflito->data_hora_fim->format('H:i').')';
                    })
                    ->hintColor('danger')
                    ->rule(function (Get $get): Closure {
                        return function (string $attribute, $value, Closure $fail) use ($get): void {
                            $agendamento = Agendamento::query()->find($get('agendamentoId'));
                            $inicio = $this->dataHoraEdicao($get);

                            if (! $agendamento || ! $inicio) {
                                return;
                            }

                            $fim = $inicio->copy()->addMinutes((int) $value);

                            $conflito = $this->conflitoDeHorario($agendamento->profissional, $inicio, $fim, $agendamento->id);

                            if ($conflito) {
                                $fail('Esse horário conflita com o atendimento de '.$conflito->paciente?->nome.'.');
                            }
                        };
                    }),

                ...$this->camposSalaComAvisoTroca(
                    profissionalId: fn (Get $get): ?int => Agendamento::query()->find($get('agendamentoId'))?->profissional_id,
                    inicio: fn (Get $get): ?Carbon => $this->dataHoraEdicao($get),
                    duracaoMinutos: fn (Get $get): int => (int) ($get('duracao_minutos') ?: Agendamento::query()->find($get('agendamentoId'))?->duracao_minutos ?? 30),
                    agendamentoIgnoradoId: fn (Get $get): ?int => $get('agendamentoId') ? (int) $get('agendamentoId') : null,
                    ativoQuando: fn (Get $get): bool => $get('acao') === 'editar',
                ),

                Toggle::make('confirmar_disponibilidade_extra')
                    ->label(fn (Get $get): HtmlString => $this->avisoDisponibilidadeExtraNaEdicaoHtml($get))
                    ->live()
                    ->dehydrated(false)
                    ->visible(fn (Get $get): bool => $get('acao') === 'editar' && $this->precisaDeDisponibilidadeExtraNaEdicao($get))
                    ->rule(function (Get $get): Closure {
                        return function (string $attribute, $value, Closure $fail) use ($get): void {
                            if ($get('acao') === 'editar' && $this->precisaDeDisponibilidadeExtraNaEdicao($get) && ! $value) {
                                $fail('Confirme a criação da disponibilidade extra para prosseguir.');
                            }
                        };
                    }),

                $this->campoProcedimentoPrevisto(fn (Get $get): ?int => $get('especialidade_id') ?: Agendamento::query()->find($get('agendamentoId'))?->especialidade_id)
                    ->visible(fn (Get $get): bool => $get('acao') === 'editar'),

                $this->campoDescricao()
                    ->visible(fn (Get $get): bool => $get('acao') === 'editar'),

                Textarea::make('resumo_atendimento')
                    ->label('Resumo do atendimento')
                    ->visible(fn (Get $get): bool => in_array($get('acao'), ['finalizar', 'editar_atendimento'], true))
                    ->columnSpanFull(),

                Repeater::make('procedimentos')
                    ->label('Procedimentos realizados')
                    ->schema([
                        Select::make('procedimento_id')
                            ->label('Procedimento')
                            ->options(function (Get $get): array {
                                $agendamento = Agendamento::query()->find($get('../../agendamentoId'));

                                return Procedimento::query()
                                    ->daClinica()
                                    ->where('ativo', true)
                                    ->when($agendamento, fn ($query) => $query->where('especialidade_id', $agendamento->especialidade_id))
                                    ->orderBy('nome')
                                    ->pluck('nome', 'id')
                                    ->all();
                            })
                            ->searchable()
                            ->required(),
                        TextInput::make('quantidade')
                            ->label('Quantidade')
                            ->numeric()
                            ->default(1)
                            ->minValue(1)
                            ->required(),
                    ])
                    ->columns(2)
                    ->addActionLabel('Adicionar procedimento')
                    ->visible(fn (Get $get): bool => in_array($get('acao'), ['finalizar', 'editar_atendimento'], true))
                    ->columnSpanFull(),

                Select::make('solicitante')
                    ->label('Solicitante do cancelamento')
                    ->options([
                        'paciente' => 'Paciente',
                        'profissional' => 'Profissional',
                        'clinica' => 'Clínica',
                        'outro' => 'Outro',
                    ])
                    ->visible(fn (Get $get): bool => $get('acao') === 'cancelar'),

                Textarea::make('motivo')
                    ->label('Motivo do cancelamento')
                    ->visible(fn (Get $get): bool => $get('acao') === 'cancelar')
                    ->columnSpanFull(),

                Toggle::make('confirmar_reversao')
                    ->label('Tem certeza que deseja voltar o status?')
                    ->dehydrated(false)
                    ->visible(fn (Get $get): bool => in_array($get('acao'), ['reverter', 'voltar_agendado'], true))
                    ->rule(function (Get $get): Closure {
                        return function (string $attribute, $value, Closure $fail) use ($get): void {
                            if (in_array($get('acao'), ['reverter', 'voltar_agendado'], true) && ! $value) {
                                $fail('Confirme que deseja voltar o status.');
                            }
                        };
                    }),
            ])
            ->action(function (array $data, array $arguments, Action $action): void {
                $agendamento = Agendamento::query()->find($arguments['agendamentoId']);

                if (! $agendamento) {
                    return;
                }

                try {
                    match ($data['acao']) {
                        'cancelar' => app(ServicoCancelamentoAgendamento::class)->executar(
                            $agendamento,
                            $data['motivo'] ?? '',
                            $data['solicitante'] ?? 'outro',
                            auth()->user(),
                        ),
                        'confirmar' => app(ServicoConfirmacaoAgendamento::class)->executar(
                            $agendamento,
                            match (true) {
                                $data['forma_confirmacao'] instanceof FormaConfirmacao => $data['forma_confirmacao'],
                                filled($data['forma_confirmacao'] ?? null) => FormaConfirmacao::from($data['forma_confirmacao']),
                                default => FormaConfirmacao::Outro,
                            },
                            auth()->user(),
                            $data['observacoes_confirmacao'] ?? null,
                        ),
                        'finalizar', 'editar_atendimento' => app(ServicoFinalizacaoAtendimento::class)->executar(
                            $agendamento,
                            [
                                'resumo_atendimento' => $data['resumo_atendimento'] ?? '',
                                'observacoes_internas' => null,
                                'procedimentos' => $data['procedimentos'] ?? [],
                            ],
                            auth()->user(),
                        ),
                        'reverter' => app(ServicoReversaoAtendimento::class)->executar($agendamento),
                        'voltar_agendado' => app(ServicoReversaoConfirmacao::class)->executar($agendamento),
                        default => app(ServicoEdicaoAgendamento::class)->executar($agendamento, [
                            'paciente_id' => $data['paciente_id'] ?? null,
                            'especialidade_id' => $data['especialidade_id'] ?? null,
                            'sala_id' => $data['sala_id'] ?? null,
                            'data_hora_inicio' => $this->combinarDataHoraEdicao($data['data_edicao'] ?? null, $data['hora_edicao'] ?? null, $data['minuto_edicao'] ?? null),
                            'duracao_minutos' => $data['duracao_minutos'] ?? null,
                            'tipo_atendimento' => $data['tipo_atendimento'] ?? null,
                            'procedimentos_previstos_ids' => $data['procedimentos_previstos_ids'] ?? [],
                            'descricao' => $data['descricao'] ?? null,
                            'confirmar_disponibilidade_extra' => (bool) ($data['confirmar_disponibilidade_extra'] ?? false),
                        ], auth()->user()),
                    };
                } catch (ValidationException $exception) {
                    Notification::make()
                        ->danger()
                        ->title('Não foi possível ajustar o agendamento')
                        ->body(collect($exception->errors())->flatten()->implode(' '))
                        ->send();

                    $action->halt();

                    return;
                }

                Notification::make()
                    ->success()
                    ->title(match ($data['acao']) {
                        'cancelar' => 'Agendamento cancelado.',
                        'confirmar' => 'Consulta confirmada.',
                        'finalizar' => 'Atendimento finalizado.',
                        'editar_atendimento' => 'Atendimento atualizado.',
                        'reverter' => 'Atendimento revertido para confirmado.',
                        'voltar_agendado' => 'Agendamento revertido para agendado.',
                        default => 'Consulta editada com sucesso.',
                    })
                    ->send();
            });
    }

    protected function conflitoDeHorario(Profissional $profissional, Carbon $inicio, Carbon $fim, ?int $agendamentoIgnoradoId = null): ?Agendamento
    {
        return Agendamento::query()
            ->where('clinica_id', $profissional->clinica_id)
            ->where('profissional_id', $profissional->id)
            ->whereIn('situacao', [
                SituacaoAgendamento::Agendado,
                SituacaoAgendamento::Confirmado,
                SituacaoAgendamento::Realizado,
            ])
            ->when($agendamentoIgnoradoId, fn ($query) => $query->whereKeyNot($agendamentoIgnoradoId))
            ->where('data_hora_inicio', '<', $fim)
            ->where('data_hora_fim', '>', $inicio)
            ->with('paciente')
            ->first();
    }

    protected function servicoDisponibilidadeAgenda(): ServicoDisponibilidadeAgenda
    {
        return app(ServicoDisponibilidadeAgenda::class);
    }

    protected function precisaDeDisponibilidadeExtraNaEdicao(Get $get): bool
    {
        $agendamento = Agendamento::query()->find($get('agendamentoId'));
        $inicio = $this->dataHoraEdicao($get);

        if (! $agendamento || ! $inicio || blank($get('duracao_minutos'))) {
            return false;
        }

        $fim = $inicio->copy()->addMinutes((int) $get('duracao_minutos'));

        return ! $this->servicoDisponibilidadeAgenda()->estaDentroDaDisponibilidade($agendamento->profissional, $inicio, $fim);
    }

    protected function avisoDisponibilidadeExtraNaEdicaoHtml(Get $get): HtmlString
    {
        $nome = Agendamento::query()->find($get('agendamentoId'))?->profissional?->nome;
        $confirmado = (bool) $get('confirmar_disponibilidade_extra');
        $cor = $confirmado ? 'text-success-600 dark:text-success-400' : 'text-danger-600 dark:text-danger-400';

        return new HtmlString(
            '<span class="'.$cor.'">'.e($nome).' não possui disponibilidade cadastrada nesse horário. '
            .'Confirmo que o profissional aceitou atender neste horário.</span>'
        );
    }

    protected function avisoTrocaSalaHtml(?string $aviso): ?HtmlString
    {
        if (blank($aviso)) {
            return null;
        }

        return new HtmlString('<span class="text-warning-600 dark:text-warning-400">'.e($aviso).'</span>');
    }

    /**
     * Campo "Sala" com aviso de troca de sala predefinida + toggle de confirmação pareado.
     * Reaproveitado por AgendarConsulta, MarcaConsultaRapida e a remarcação deste trait —
     * cada chamador só resolve profissional/início/duração/exclusão do jeito que fizer sentido
     * pro seu formulário (agendamento em edição, form de busca, ação da dashboard, etc.).
     *
     * @return array{0: Select, 1: Toggle}
     * @param  Closure(Get): ?int  $profissionalId
     * @param  Closure(Get): ?Carbon  $inicio
     * @param  Closure(Get): int  $duracaoMinutos
     * @param  (Closure(Get): ?int)|null  $agendamentoIgnoradoId
     * @param  (Closure(Get): bool)|null  $ativoQuando
     */
    protected function camposSalaComAvisoTroca(
        Closure $profissionalId,
        Closure $inicio,
        Closure $duracaoMinutos,
        ?Closure $agendamentoIgnoradoId = null,
        ?Closure $ativoQuando = null,
    ): array {
        $ocupacao = function (Get $get) use ($profissionalId, $inicio, $duracaoMinutos, $agendamentoIgnoradoId): Collection {
            $profissionalIdResolvido = $profissionalId($get);
            $inicioResolvido = $inicio($get);

            if (blank($profissionalIdResolvido) || ! $inicioResolvido) {
                return collect();
            }

            return $this->salasComOcupacao(
                $profissionalIdResolvido,
                $inicioResolvido,
                $duracaoMinutos($get),
                $agendamentoIgnoradoId ? $agendamentoIgnoradoId($get) : null,
            );
        };

        $aviso = function (Get $get) use ($profissionalId, $inicio, $duracaoMinutos): ?string {
            $profissionalIdResolvido = $profissionalId($get);
            $inicioResolvido = $inicio($get);

            if (blank($get('sala_id')) || blank($profissionalIdResolvido) || ! $inicioResolvido) {
                return null;
            }

            $fim = $inicioResolvido->copy()->addMinutes($duracaoMinutos($get));

            return app(ServicoConflitoSalaPredefinida::class)->avisoTrocaSala(
                (int) $get('sala_id'),
                $profissionalIdResolvido,
                $inicioResolvido,
                $fim,
            );
        };

        $ativo = fn (Get $get): bool => $ativoQuando === null || $ativoQuando($get);

        $sala = Select::make('sala_id')
            ->label('Sala')
            ->searchable()
            ->live()
            ->options(fn (Get $get): array => $ocupacao($get)
                ->mapWithKeys(fn (array $sala): array => [$sala['id'] => $sala['nome'].($sala['ocupada'] ? ' (ocupada)' : '')])
                ->all())
            ->disableOptionWhen(fn (Get $get, mixed $value): bool => (bool) ($ocupacao($get)->firstWhere('id', (int) $value)['ocupada'] ?? false))
            ->helperText(fn (Get $get): ?HtmlString => $this->avisoTrocaSalaHtml($aviso($get)))
            ->validationMessages([
                'in' => 'Essa sala não está mais disponível para esse horário (lotada ou trocada). Selecione outra sala.',
            ])
            ->visible($ativo)
            ->required($ativo);

        $toggle = Toggle::make('confirmar_troca_sala')
            ->label('Confirmo que quero usar essa sala mesmo assim')
            ->dehydrated(false)
            ->visible(fn (Get $get): bool => $ativo($get) && filled($aviso($get)))
            ->rule(function (Get $get) use ($ativo, $aviso): Closure {
                return function (string $attribute, $value, Closure $fail) use ($get, $ativo, $aviso): void {
                    if ($ativo($get) && filled($aviso($get)) && ! $value) {
                        $fail('Confirme que deseja usar essa sala.');
                    }
                };
            });

        return [$sala, $toggle];
    }

    /**
     * @return Collection<int, array{id: int, nome: string, ocupada: bool}>
     */
    protected function salasComOcupacao(int $profissionalId, Carbon $inicio, int $duracaoMinutos, ?int $agendamentoIgnoradoId = null): Collection
    {
        $chave = implode('|', [$profissionalId, $inicio->format('YmdHis'), $duracaoMinutos, $agendamentoIgnoradoId]);

        if (isset($this->cacheSalasComOcupacao[$chave])) {
            return $this->cacheSalasComOcupacao[$chave];
        }

        $fim = $inicio->copy()->addMinutes($duracaoMinutos);

        return $this->cacheSalasComOcupacao[$chave] = Sala::query()
            ->daClinica()
            ->where('ativo', true)
            ->whereHas('profissionais', fn ($query) => $query->whereKey($profissionalId))
            ->orderBy('nome')
            ->get()
            ->map(function (Sala $sala) use ($inicio, $fim, $agendamentoIgnoradoId): array {
                $ocupacao = Agendamento::query()
                    ->where('sala_id', $sala->id)
                    ->whereIn('situacao', [
                        SituacaoAgendamento::Agendado->value,
                        SituacaoAgendamento::Confirmado->value,
                        SituacaoAgendamento::Realizado->value,
                    ])
                    ->when($agendamentoIgnoradoId, fn ($query) => $query->whereKeyNot($agendamentoIgnoradoId))
                    ->where('data_hora_inicio', '<', $fim)
                    ->where('data_hora_fim', '>', $inicio)
                    ->count();

                return [
                    'id' => $sala->id,
                    'nome' => $sala->nome,
                    'ocupada' => $ocupacao >= $sala->capacidade_atendimentos_simultaneos,
                ];
            })
            ->values();
    }
}
