<?php

namespace App\Livewire\Profissionais;

use App\Enums\FormaConfirmacao;
use App\Enums\SituacaoAgendamento;
use App\Filament\Pages\Concerns\CamposConsulta;
use App\Filament\Support\ResumoAgendamentoSchema;
use App\Models\Agendamento;
use App\Models\Clinica;
use App\Models\Procedimento;
use App\Models\Profissional;
use App\Models\Sala;
use App\Services\ServicoCancelamentoAgendamento;
use App\Services\ServicoConfirmacaoAgendamento;
use App\Services\ServicoConflitoSalaPredefinida;
use App\Services\ServicoCriacaoAgendamento;
use App\Services\ServicoDisponibilidadeAgenda;
use App\Services\ServicoEdicaoAgendamento;
use App\Services\ServicoFinalizacaoAtendimento;
use App\Services\ServicoIntervaloAgenda;
use App\Support\DuracaoAtendimento;
use Carbon\Carbon;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Support\Collection;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Reactive;

class AgendaTabela extends TableWidget
{
    use CamposConsulta;

    #[Reactive]
    public ?int $profissionalId = null;

    #[Reactive]
    public string $visao = 'dia';

    #[Reactive]
    public string $data;

    public function table(Table $table): Table
    {
        return $table
            ->records(fn (): array => $this->montarLinhas())
            ->columns([
                TextColumn::make('horario')
                    ->label('Horário')
                    ->state(fn (array $record) => $record['inicio']->format('H:i').' - '.$record['fim']->format('H:i')),
                TextColumn::make('paciente')->label('Paciente')->placeholder('—'),
                TextColumn::make('especialidade')->label('Especialidade')->placeholder('—'),
                TextColumn::make('sala')->label('Sala')->placeholder('—'),
                TextColumn::make('situacao')
                    ->label('Situação')
                    ->badge()
                    ->state(fn (array $record) => $record['tipo'] === 'livre' ? 'Livre' : $record['situacao']->getLabel())
                    ->color(fn (array $record) => $record['tipo'] === 'livre' ? 'success' : null),
            ])
            ->recordActions([
                Action::make('marcar')
                    ->label('Marcar')
                    ->icon('heroicon-o-plus-circle')
                    ->color('success')
                    ->visible(fn (array $record): bool => $record['tipo'] === 'livre')
                    ->modalHeading(fn (array $record): string => 'Agendar '.$record['inicio']->translatedFormat('d/m/Y \à\s H:i'))
                    ->modalSubmitActionLabel('Confirmar agendamento')
                    ->modalWidth('lg')
                    ->fillForm(function (array $record): array {
                        $profissional = Profissional::query()->find($this->profissionalId);
                        $duracao = $record['duracao_minutos'] ?? ($profissional?->duracao_padrao_atendimento ?? 30);

                        return [
                            'especialidade_id' => $record['especialidade_id'],
                            'tipo_atendimento' => 'consulta',
                            'duracao_minutos' => $duracao,
                            'sala_id' => $this->salaPredefinidaParaSlot($record, $duracao),
                        ];
                    })
                    ->schema(function (array $record): array {
                        $profissional = Profissional::query()->find($this->profissionalId);
                        $especialidades = $profissional?->especialidades ?? collect();

                        return [
                            Select::make('especialidade_id')
                                ->label('Especialidade')
                                ->options($especialidades->pluck('nome', 'id'))
                                ->visible($especialidades->count() > 1)
                                ->live()
                                ->required(),

                            Radio::make('tipo_atendimento')
                                ->label('Tipo de atendimento')
                                ->options([
                                    'consulta' => 'Consulta',
                                    'retorno' => 'Retorno',
                                ])
                                ->inline()
                                ->visible((bool) $profissional?->oferece_retorno),

                            Select::make('duracao_minutos')
                                ->label('Duração')
                                ->options(DuracaoAtendimento::options())
                                ->required()
                                ->live()
                                ->hint(function (Get $get) use ($record): ?string {
                                    $profissional = Profissional::query()->find($this->profissionalId);
                                    $duracao = (int) ($get('duracao_minutos') ?: 0);

                                    if (! $profissional || ! $duracao) {
                                        return null;
                                    }

                                    $conflito = $this->conflitoDeHorario($profissional, $record['inicio'], $record['inicio']->copy()->addMinutes($duracao));

                                    if (! $conflito) {
                                        return null;
                                    }

                                    return 'Conflita com '.$conflito->paciente?->nome.' ('.$conflito->data_hora_inicio->format('H:i').' às '.$conflito->data_hora_fim->format('H:i').')';
                                })
                                ->hintColor('danger')
                                ->rule(function () use ($record): Closure {
                                    return function (string $attribute, $value, Closure $fail) use ($record): void {
                                        $profissional = Profissional::query()->find($this->profissionalId);

                                        if (! $profissional) {
                                            return;
                                        }

                                        $conflito = $this->conflitoDeHorario($profissional, $record['inicio'], $record['inicio']->copy()->addMinutes((int) $value));

                                        if ($conflito) {
                                            $fail('Esse horário conflita com o atendimento de '.$conflito->paciente?->nome.'.');
                                        }
                                    };
                                }),

                            Select::make('sala_id')
                                ->label('Sala')
                                ->required()
                                ->live()
                                ->options(function (Get $get) use ($record): array {
                                    $slot = ['inicio' => $record['inicio'], 'duracao_minutos' => $get('duracao_minutos') ?: $record['duracao_minutos'] ?? 30];

                                    return $this->salasComOcupacaoParaSlot($slot)
                                        ->mapWithKeys(fn (array $sala): array => [$sala['id'] => $sala['nome'].($sala['ocupada'] ? ' (ocupada)' : '')])
                                        ->all();
                                })
                                ->disableOptionWhen(function (Get $get, mixed $value) use ($record): bool {
                                    $slot = ['inicio' => $record['inicio'], 'duracao_minutos' => $get('duracao_minutos') ?: $record['duracao_minutos'] ?? 30];

                                    return (bool) ($this->salasComOcupacaoParaSlot($slot)
                                        ->firstWhere('id', (int) $value)['ocupada'] ?? false);
                                })
                                ->helperText(function (Get $get) use ($record): ?HtmlString {
                                    return $this->avisoTrocaSalaHtml($this->avisoTrocaSalaParaSlot($record, $get('sala_id'), $get('duracao_minutos')));
                                })
                                ->validationMessages([
                                    'in' => 'Essa sala não está mais disponível para esse horário (lotada ou trocada). Selecione outra sala.',
                                ]),

                            Toggle::make('confirmar_troca_sala')
                                ->label('Confirmo que quero usar essa sala mesmo assim')
                                ->dehydrated(false)
                                ->visible(fn (Get $get): bool => filled($this->avisoTrocaSalaParaSlot($record, $get('sala_id'), $get('duracao_minutos'))))
                                ->rule(function (Get $get) use ($record): Closure {
                                    return function (string $attribute, $value, Closure $fail) use ($get, $record): void {
                                        if (filled($this->avisoTrocaSalaParaSlot($record, $get('sala_id'), $get('duracao_minutos'))) && ! $value) {
                                            $fail('Confirme que deseja usar essa sala.');
                                        }
                                    };
                                }),

                            $this->campoPaciente(),
                            $this->campoProcedimentoPrevisto(fn (Get $get): ?int => $get('especialidade_id') ?: $record['especialidade_id']),
                            $this->campoDescricao(),
                        ];
                    })
                    ->action(function (array $data, array $record): void {
                        try {
                            app(ServicoCriacaoAgendamento::class)->executar([
                                'paciente_id' => $data['paciente_id'],
                                'profissional_id' => $this->profissionalId,
                                'especialidade_id' => $data['especialidade_id'] ?? $record['especialidade_id'],
                                'procedimentos_previstos_ids' => $data['procedimentos_previstos_ids'] ?? [],
                                'sala_id' => $data['sala_id'],
                                'data_hora_inicio' => $record['inicio'],
                                'duracao_minutos' => $data['duracao_minutos'],
                                'tipo_atendimento' => $data['tipo_atendimento'] ?? 'consulta',
                                'descricao' => $data['descricao'] ?? null,
                            ], auth()->user());
                        } catch (ValidationException $exception) {
                            Notification::make()
                                ->danger()
                                ->title('Não foi possível agendar')
                                ->body(collect($exception->errors())->flatten()->implode(' '))
                                ->send();

                            return;
                        }

                        $this->flushCachedTableRecords();

                        Notification::make()->success()->title('Agendamento criado com sucesso.')->send();
                    }),

                Action::make('resumo')
                    ->label('Ver resumo')
                    ->icon('heroicon-o-eye')
                    ->color('gray')
                    ->visible(fn (array $record): bool => $record['tipo'] === 'ocupado')
                    ->modalHeading(fn (array $record): string => 'Resumo da consulta de '.($record['paciente'] ?? ''))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Fechar')
                    ->modalWidth('2xl')
                    ->schema(function (array $record): array {
                        $agendamento = Agendamento::query()
                            ->with(['paciente', 'profissional', 'especialidade', 'sala', 'procedimentos', 'procedimentosPrevistos', 'registroAtendimento'])
                            ->findOrFail($record['agendamento_id']);

                        return ResumoAgendamentoSchema::schema($agendamento);
                    }),

                Action::make('ajustar')
                    ->label('Ajustar')
                    ->icon('heroicon-o-pencil-square')
                    ->color('gray')
                    ->visible(fn (array $record): bool => $record['tipo'] === 'ocupado'
                        && in_array($record['situacao'], [SituacaoAgendamento::Agendado, SituacaoAgendamento::Confirmado], true))
                    ->modalHeading(fn (array $record): string => 'Ajustar agendamento de '.($record['paciente'] ?? ''))
                    ->modalSubmitActionLabel('Salvar')
                    ->modalWidth('2xl')
                    ->fillForm(function (array $record): array {
                        $agendamento = Agendamento::query()->with('procedimentosPrevistos')->find($record['agendamento_id']);

                        return [
                            'paciente_id' => $agendamento?->paciente_id,
                            'especialidade_id' => $agendamento?->especialidade_id,
                            'tipo_atendimento' => $agendamento?->tipo_atendimento,
                            'data_edicao' => $record['inicio']->toDateString(),
                            'hora_edicao' => (int) $record['inicio']->format('H'),
                            'minuto_edicao' => (int) $record['inicio']->format('i'),
                            'sala_id' => $record['sala_id'],
                            'duracao_minutos' => $record['duracao_minutos'],
                            'procedimentos_previstos_ids' => $agendamento?->procedimentosPrevistos->pluck('id')->all() ?? [],
                            'descricao' => $agendamento?->descricao,
                            'procedimentos' => $agendamento?->procedimentosPrevistos->map(fn (Procedimento $procedimento): array => [
                                'procedimento_id' => $procedimento->id,
                                'quantidade' => 1,
                            ])->all() ?? [],
                        ];
                    })
                    ->schema(fn (array $record): array => [
                        Placeholder::make('descricaoInfo')
                            ->hiddenLabel()
                            ->content(fn (): ?string => Agendamento::query()->find($record['agendamento_id'])?->descricao)
                            ->visible(fn (Get $get): bool => $get('acao') !== 'editar'
                                && filled(Agendamento::query()->find($record['agendamento_id'])?->descricao)),

                        Radio::make('acao')
                            ->label('O que deseja fazer?')
                            ->options(match ($record['situacao']) {
                                SituacaoAgendamento::Agendado => [
                                    'confirmar' => 'Confirmar consulta',
                                    'editar' => 'Editar consulta',
                                    'cancelar' => 'Cancelar agendamento',
                                ],
                                SituacaoAgendamento::Confirmado => [
                                    'editar' => 'Editar consulta',
                                    'finalizar' => 'Finalizar atendimento',
                                    'cancelar' => 'Cancelar agendamento',
                                ],
                                default => [],
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
                            ->visible(fn (Get $get): bool => $get('acao') === 'editar'
                                && (Profissional::query()->find($this->profissionalId)?->especialidades->count() ?? 0) > 1)
                            ->options(fn (): array => Profissional::query()->find($this->profissionalId)?->especialidades->pluck('nome', 'id')->all() ?? [])
                            ->required(fn (Get $get): bool => $get('acao') === 'editar'),

                        Radio::make('tipo_atendimento')
                            ->label('Tipo de atendimento')
                            ->options(['consulta' => 'Consulta', 'retorno' => 'Retorno'])
                            ->inline()
                            ->visible(fn (Get $get): bool => $get('acao') === 'editar'
                                && (bool) Profissional::query()->find($this->profissionalId)?->oferece_retorno),

                        ...$this->camposDataHoraEdicao(
                            fn (Get $get): bool => $get('acao') === 'editar',
                            fn (): ?Clinica => Profissional::query()->find($this->profissionalId)?->clinica,
                        ),

                        Select::make('duracao_minutos')
                            ->label('Duração')
                            ->options(DuracaoAtendimento::options())
                            ->live()
                            ->visible(fn (Get $get): bool => $get('acao') === 'editar')
                            ->required(fn (Get $get): bool => $get('acao') === 'editar')
                            ->hint(function (Get $get) use ($record): ?string {
                                $profissional = Profissional::query()->find($this->profissionalId);
                                $duracao = (int) ($get('duracao_minutos') ?: 0);
                                $inicio = $this->dataHoraEdicao($get);

                                if (! $profissional || ! $duracao || ! $inicio) {
                                    return null;
                                }

                                $fim = $inicio->copy()->addMinutes($duracao);

                                if (! app(ServicoDisponibilidadeAgenda::class)->estaDentroDaDisponibilidade($profissional, $inicio, $fim)) {
                                    return 'Fora da disponibilidade cadastrada do profissional nesse dia.';
                                }

                                $conflito = $this->conflitoDeHorario($profissional, $inicio, $fim, $record['agendamento_id']);

                                if (! $conflito) {
                                    return null;
                                }

                                return 'Conflita com '.$conflito->paciente?->nome.' ('.$conflito->data_hora_inicio->format('H:i').' às '.$conflito->data_hora_fim->format('H:i').')';
                            })
                            ->hintColor('danger')
                            ->rule(function (Get $get) use ($record): Closure {
                                return function (string $attribute, $value, Closure $fail) use ($get, $record): void {
                                    $profissional = Profissional::query()->find($this->profissionalId);
                                    $inicio = $this->dataHoraEdicao($get);

                                    if (! $profissional || ! $inicio) {
                                        return;
                                    }

                                    $conflito = $this->conflitoDeHorario($profissional, $inicio, $inicio->copy()->addMinutes((int) $value), $record['agendamento_id']);

                                    if ($conflito) {
                                        $fail('Esse horário conflita com o atendimento de '.$conflito->paciente?->nome.'.');
                                    }
                                };
                            }),

                        Select::make('sala_id')
                            ->label('Sala')
                            ->searchable()
                            ->live()
                            ->options(function (Get $get) use ($record): array {
                                $inicio = $this->dataHoraEdicao($get);

                                if (! $inicio) {
                                    return [];
                                }

                                $duracao = (int) ($get('duracao_minutos') ?: ($record['duracao_minutos'] ?? 30));

                                return $this->salasComOcupacaoParaSlot([
                                    'inicio' => $inicio,
                                    'duracao_minutos' => $duracao,
                                ], $record['agendamento_id'])
                                    ->mapWithKeys(fn (array $sala): array => [$sala['id'] => $sala['nome'].($sala['ocupada'] ? ' (ocupada)' : '')])
                                    ->all();
                            })
                            ->disableOptionWhen(function (Get $get, mixed $value) use ($record): bool {
                                $inicio = $this->dataHoraEdicao($get);

                                if (! $inicio) {
                                    return false;
                                }

                                $duracao = (int) ($get('duracao_minutos') ?: ($record['duracao_minutos'] ?? 30));

                                return (bool) ($this->salasComOcupacaoParaSlot([
                                    'inicio' => $inicio,
                                    'duracao_minutos' => $duracao,
                                ], $record['agendamento_id'])->firstWhere('id', (int) $value)['ocupada'] ?? false);
                            })
                            ->helperText(function (Get $get): ?HtmlString {
                                $duracao = (int) ($get('duracao_minutos') ?: 30);

                                return $this->avisoTrocaSalaHtml($this->avisoTrocaSalaParaDataHora($this->dataHoraEdicao($get)?->toDateTimeString(), $duracao, $get('sala_id')));
                            })
                            ->validationMessages([
                                'in' => 'Essa sala não está mais disponível para esse horário (lotada ou trocada). Selecione outra sala.',
                            ])
                            ->visible(fn (Get $get): bool => $get('acao') === 'editar')
                            ->required(fn (Get $get): bool => $get('acao') === 'editar'),

                        Toggle::make('confirmar_troca_sala')
                            ->label('Confirmo que quero usar essa sala mesmo assim')
                            ->dehydrated(false)
                            ->visible(function (Get $get): bool {
                                if ($get('acao') !== 'editar') {
                                    return false;
                                }

                                $duracao = (int) ($get('duracao_minutos') ?: 30);

                                return filled($this->avisoTrocaSalaParaDataHora($this->dataHoraEdicao($get)?->toDateTimeString(), $duracao, $get('sala_id')));
                            })
                            ->rule(function (Get $get): Closure {
                                return function (string $attribute, $value, Closure $fail) use ($get): void {
                                    if ($get('acao') !== 'editar') {
                                        return;
                                    }

                                    $duracao = (int) ($get('duracao_minutos') ?: 30);

                                    if (filled($this->avisoTrocaSalaParaDataHora($this->dataHoraEdicao($get)?->toDateTimeString(), $duracao, $get('sala_id'))) && ! $value) {
                                        $fail('Confirme que deseja usar essa sala.');
                                    }
                                };
                            }),

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

                        $this->campoProcedimentoPrevisto(fn (Get $get): ?int => $get('especialidade_id') ?: $record['especialidade_id'])
                            ->visible(fn (Get $get): bool => $get('acao') === 'editar'),

                        $this->campoDescricao()
                            ->visible(fn (Get $get): bool => $get('acao') === 'editar'),

                        Textarea::make('resumo_atendimento')
                            ->label('Resumo do atendimento')
                            ->visible(fn (Get $get): bool => $get('acao') === 'finalizar')
                            ->columnSpanFull(),

                        Textarea::make('observacoes_internas')
                            ->label('Observações internas')
                            ->visible(fn (Get $get): bool => $get('acao') === 'finalizar')
                            ->columnSpanFull(),

                        Repeater::make('procedimentos')
                            ->label('Procedimentos realizados')
                            ->schema([
                                Select::make('procedimento_id')
                                    ->label('Procedimento')
                                    ->options(fn (): array => Procedimento::query()
                                        ->daClinica()
                                        ->where('ativo', true)
                                        ->where('especialidade_id', $record['especialidade_id'])
                                        ->orderBy('nome')
                                        ->pluck('nome', 'id')
                                        ->all())
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
                            ->visible(fn (Get $get): bool => $get('acao') === 'finalizar')
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
                    ])
                    ->action(function (array $data, array $record): void {
                        $agendamento = Agendamento::query()->find($record['agendamento_id']);

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
                                        ($data['forma_confirmacao'] ?? null) instanceof FormaConfirmacao => $data['forma_confirmacao'],
                                        filled($data['forma_confirmacao'] ?? null) => FormaConfirmacao::from($data['forma_confirmacao']),
                                        default => FormaConfirmacao::Outro,
                                    },
                                    auth()->user(),
                                    $data['observacoes_confirmacao'] ?? null,
                                ),
                                'finalizar' => app(ServicoFinalizacaoAtendimento::class)->executar(
                                    $agendamento,
                                    [
                                        'resumo_atendimento' => $data['resumo_atendimento'] ?? '',
                                        'observacoes_internas' => $data['observacoes_internas'] ?? null,
                                        'procedimentos' => $data['procedimentos'] ?? [],
                                    ],
                                    auth()->user(),
                                ),
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

                            return;
                        }

                        $this->flushCachedTableRecords();

                        Notification::make()
                            ->success()
                            ->title(match ($data['acao']) {
                                'cancelar' => 'Agendamento cancelado.',
                                'confirmar' => 'Consulta confirmada.',
                                'finalizar' => 'Atendimento finalizado.',
                                default => 'Consulta editada com sucesso.',
                            })
                            ->send();
                    }),
            ])
            ->defaultGroup(
                Group::make('data')
                    ->getTitleFromRecordUsing(fn (array $record) => $record['inicio']->translatedFormat('d/m/Y (l)'))
            )
            ->groupingSettingsHidden()
            ->heading(null)
            ->paginated(false)
            ->emptyStateHeading('Nenhuma agenda cadastrada para esse profissional na data selecionada');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function montarLinhas(): array
    {
        if (! $this->profissionalId) {
            return [];
        }

        $profissional = Profissional::query()->find($this->profissionalId);

        if (! $profissional) {
            return [];
        }

        [$inicio, $fim] = app(ServicoIntervaloAgenda::class)->intervalo(Carbon::parse($this->data), $this->visao);

        $ocupados = Agendamento::query()
            ->where('profissional_id', $this->profissionalId)
            ->whereBetween('data_hora_inicio', [$inicio, $fim])
            ->whereNotIn('situacao', [SituacaoAgendamento::Cancelado, SituacaoAgendamento::Remarcado])
            ->with(['paciente', 'especialidade', 'sala'])
            ->get()
            ->map(fn (Agendamento $agendamento): array => [
                '__key' => 'ocupado-'.$agendamento->id,
                'tipo' => 'ocupado',
                'agendamento_id' => $agendamento->id,
                'data' => $agendamento->data_hora_inicio->toDateString(),
                'inicio' => $agendamento->data_hora_inicio,
                'fim' => $agendamento->data_hora_fim,
                'duracao_minutos' => $agendamento->data_hora_inicio->diffInMinutes($agendamento->data_hora_fim),
                'paciente' => $agendamento->paciente?->nome,
                'especialidade' => $agendamento->especialidade?->nome,
                'especialidade_id' => $agendamento->especialidade_id,
                'sala' => $agendamento->sala?->nome,
                'sala_id' => $agendamento->sala_id,
                'situacao' => $agendamento->situacao,
            ]);

        $livres = $this->montarLivres($profissional, $inicio, $fim);

        return $ocupados->concat($livres)
            ->sortBy(fn (array $registro) => $registro['inicio']->format('Y-m-d H:i:s'))
            ->values()
            ->all();
    }

    protected function montarLivres(Profissional $profissional, Carbon $inicio, Carbon $fim): Collection
    {
        $especialidades = $profissional->especialidades;

        if ($especialidades->isEmpty()) {
            return collect();
        }

        $servico = app(ServicoDisponibilidadeAgenda::class);

        $cursor = $inicio->copy()->startOfDay();
        $hoje = now()->startOfDay();

        if ($cursor->lt($hoje)) {
            $cursor = $hoje->copy();
        }

        $livres = collect();

        while ($cursor->lte($fim)) {
            foreach ($especialidades as $especialidade) {
                foreach ($servico->horariosDisponiveis($profissional, $cursor->copy(), $especialidade->id) as $slot) {
                    $livres->push([
                        '__key' => 'livre-'.$slot['inicio']->format('YmdHis').'-'.$especialidade->id,
                        'tipo' => 'livre',
                        'data' => $slot['inicio']->toDateString(),
                        'inicio' => $slot['inicio'],
                        'fim' => $slot['fim'],
                        'duracao_minutos' => $slot['duracao_minutos'],
                        'paciente' => null,
                        'especialidade' => $especialidade->nome,
                        'especialidade_id' => $especialidade->id,
                        'sala' => null,
                        'situacao' => null,
                    ]);
                }
            }

            $cursor->addDay();
        }

        return $livres->unique(fn (array $slot) => $slot['inicio']->format('Y-m-d H:i:s'))->values();
    }

    protected function conflitoDeHorario(Profissional $profissional, Carbon $inicio, Carbon $fim, ?int $agendamentoIgnoradoId = null): ?Agendamento
    {
        return Agendamento::query()
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

    protected function salaPredefinidaParaSlot(?array $slot, mixed $duracaoMinutos = null): ?int
    {
        if (! $slot || ! $this->profissionalId) {
            return null;
        }

        $duracao = (int) ($duracaoMinutos ?: ($slot['duracao_minutos'] ?? 30));
        $inicio = $slot['inicio'];

        return app(ServicoConflitoSalaPredefinida::class)->salaPredefinida($this->profissionalId, $inicio, $inicio->copy()->addMinutes($duracao));
    }

    protected function avisoTrocaSalaHtml(?string $aviso): ?HtmlString
    {
        if (blank($aviso)) {
            return null;
        }

        return new HtmlString('<span class="text-warning-600 dark:text-warning-400">'.e($aviso).'</span>');
    }

    protected function precisaDeDisponibilidadeExtraNaEdicao(Get $get): bool
    {
        $profissional = Profissional::query()->find($this->profissionalId);
        $inicio = $this->dataHoraEdicao($get);

        if (! $profissional || ! $inicio || blank($get('duracao_minutos'))) {
            return false;
        }

        $fim = $inicio->copy()->addMinutes((int) $get('duracao_minutos'));

        return ! app(ServicoDisponibilidadeAgenda::class)->estaDentroDaDisponibilidade($profissional, $inicio, $fim);
    }

    protected function avisoDisponibilidadeExtraNaEdicaoHtml(Get $get): HtmlString
    {
        $nome = Profissional::query()->find($this->profissionalId)?->nome;
        $confirmado = (bool) $get('confirmar_disponibilidade_extra');
        $cor = $confirmado ? 'text-success-600 dark:text-success-400' : 'text-danger-600 dark:text-danger-400';

        return new HtmlString(
            '<span class="'.$cor.'">'.e($nome).' não possui disponibilidade cadastrada nesse horário. '
            .'Confirmo que o profissional aceitou atender neste horário.</span>'
        );
    }

    protected function avisoTrocaSalaParaSlot(?array $slot, mixed $salaEscolhidaId, mixed $duracaoMinutos = null): ?string
    {
        if (! $slot || ! $this->profissionalId || blank($salaEscolhidaId)) {
            return null;
        }

        $duracao = (int) ($duracaoMinutos ?: ($slot['duracao_minutos'] ?? 30));
        $inicio = $slot['inicio'];

        return app(ServicoConflitoSalaPredefinida::class)->avisoTrocaSala(
            (int) $salaEscolhidaId,
            $this->profissionalId,
            $inicio,
            $inicio->copy()->addMinutes($duracao),
        );
    }

    protected function avisoTrocaSalaParaDataHora(?string $dataHoraInicio, ?int $duracaoMinutos, mixed $salaEscolhidaId): ?string
    {
        if (blank($dataHoraInicio) || ! $this->profissionalId || blank($salaEscolhidaId)) {
            return null;
        }

        $duracao = $duracaoMinutos ?: 30;
        $inicio = Carbon::parse($dataHoraInicio);

        return app(ServicoConflitoSalaPredefinida::class)->avisoTrocaSala(
            (int) $salaEscolhidaId,
            $this->profissionalId,
            $inicio,
            $inicio->copy()->addMinutes($duracao),
        );
    }

    /**
     * @var array<string, Collection>
     */
    protected array $cacheSalasComOcupacao = [];

    /**
     * @return Collection<int, array{id: int, nome: string, ocupada: bool}>
     */
    protected function salasComOcupacaoParaSlot(?array $slot, ?int $agendamentoIgnoradoId = null): Collection
    {
        if (! $slot || ! $this->profissionalId) {
            return collect();
        }

        $inicio = $slot['inicio'];
        $duracao = $slot['duracao_minutos'] ?? 30;
        $chave = implode('|', [$this->profissionalId, $inicio->format('YmdHis'), $duracao, $agendamentoIgnoradoId]);

        if (isset($this->cacheSalasComOcupacao[$chave])) {
            return $this->cacheSalasComOcupacao[$chave];
        }

        $fim = $inicio->copy()->addMinutes($duracao);

        return $this->cacheSalasComOcupacao[$chave] = Sala::query()
            ->where('ativo', true)
            ->whereHas('profissionais', fn ($query) => $query->whereKey($this->profissionalId))
            ->orderBy('nome')
            ->get()
            ->map(function (Sala $sala) use ($inicio, $fim, $agendamentoIgnoradoId): array {
                $ocupacao = Agendamento::query()
                    ->where('sala_id', $sala->id)
                    ->whereIn('situacao', [
                        SituacaoAgendamento::Agendado,
                        SituacaoAgendamento::Confirmado,
                        SituacaoAgendamento::Realizado,
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
