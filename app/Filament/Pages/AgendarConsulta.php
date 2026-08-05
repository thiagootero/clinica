<?php

namespace App\Filament\Pages;

use App\Filament\Pages\Concerns\CamposConsulta;
use App\Filament\Pages\Concerns\GerenciaAgendamento;
use App\Models\Agendamento;
use App\Models\Especialidade;
use App\Models\Profissional;
use App\Models\Sala;
use App\Support\DuracaoAtendimento;
use App\Services\ServicoConflitoSalaPredefinida;
use App\Services\ServicoCriacaoAgendamento;
use App\Services\ServicoDisponibilidadeAgenda;
use App\Enums\SituacaoAgendamento;
use Carbon\Carbon;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Collection;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\ValidationException;
use UnitEnum;

class AgendarConsulta extends Page
{
    use CamposConsulta;
    use GerenciaAgendamento;

    protected static ?string $navigationLabel = 'Agendar consulta';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-calendar-date-range';

    protected static string|UnitEnum|null $navigationGroup = 'Agenda';

    protected static ?int $navigationSort = 1;

    protected string $view = 'filament.pages.agendar-consulta';

    public ?array $data = [];

    public int $mesCalendario;

    public int $anoCalendario;

    public function mount(): void
    {
        $this->mesCalendario = now()->month;
        $this->anoCalendario = now()->year;

        $this->form->fill();
    }

    public static function canAccess(array $parameters = []): bool
    {
        return (bool) auth()->user()?->can('create', Agendamento::class);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Buscar profissional')
                ->schema([
                    Select::make('profissional_id')
                        ->label('Profissional')
                        ->options(Profissional::query()->daClinica()->where('ativo', true)->orderBy('nome')->pluck('nome', 'id'))
                        ->searchable()
                        ->live()
                        ->required()
                        ->columnSpan(1)
                        ->afterStateUpdated(function (Set $set, ?string $state) {
                            $set('especialidade_id', null);
                            $set('data', null);

                            $especialidades = $this->especialidadesDoProfissional($state);

                            if ($especialidades->count() === 1) {
                                $set('especialidade_id', $especialidades->first());
                            }
                        }),

                    Placeholder::make('seletorDia')
                        ->label('Dia')
                        ->content(fn () => $this->renderSeletorDia())
                        ->columnSpan(1),

                    Select::make('especialidade_id')
                        ->label('Especialidade')
                        ->visible(fn (Get $get): bool => filled($get('profissional_id')) && $this->especialidadesDoProfissional($get('profissional_id'))->count() > 1)
                        ->options(fn (Get $get): array => Especialidade::query()
                            ->daClinica()
                            ->where('ativo', true)
                            ->whereKey($this->especialidadesDoProfissional($get('profissional_id')))
                            ->orderBy('nome')
                            ->pluck('nome', 'id')
                            ->all())
                        ->searchable()
                        ->live()
                        ->required(fn (Get $get): bool => filled($get('profissional_id')))
                        ->columnSpanFull()
                        ->afterStateUpdated(fn (Set $set) => $set('data', null)),
                ])
                ->columns(2),

            Section::make()
                ->heading(function (Get $get): ?string {
                    if (blank($get('data'))) {
                        return 'Agenda do dia';
                    }

                    $profissional = Profissional::query()->find($get('profissional_id'));
                    $nomeProfissional = $profissional ? $profissional->nome.' de ' : '';

                    return 'Agenda de '.$nomeProfissional.ucfirst(Carbon::parse($get('data'))->translatedFormat('l, j \d\e F \d\e Y'));
                })
                ->visible(fn (Get $get): bool => filled($get('profissional_id')) && filled($get('especialidade_id')) && filled($get('data')))
                ->schema([
                    Placeholder::make('avisoSomenteVisualizacao')
                        ->hiddenLabel()
                        ->content(function (Get $get): ?HtmlString {
                            $data = Carbon::parse($get('data'));

                            if (! $this->diaSomenteVisualizacao($data)) {
                                return null;
                            }

                            return new HtmlString('<p class="mb-2 text-xs font-medium text-gray-500 dark:text-gray-400">Somente visualização — data passada, não é possível agendar.</p>');
                        })
                        ->columnSpanFull(),
                    Placeholder::make('agendaDoDia')
                        ->hiddenLabel()
                        ->content(fn () => $this->renderAgendaDia())
                        ->columnSpanFull(),
                ])
                ->columns(1),
        ])->statePath('data');
    }

    public function agendarHorarioAction(): Action
    {
        return Action::make('agendarHorario')
            ->modalHeading(fn (array $arguments): string => 'Agendar '.Carbon::parse($arguments['inicio'])->translatedFormat('d/m/Y \à\s H:i'))
            ->modalSubmitActionLabel('Confirmar agendamento')
            ->modalWidth('lg')
            ->fillForm(function (array $arguments): array {
                $profissionalId = data_get($this->data, 'profissional_id');
                $profissional = Profissional::query()->find($profissionalId);
                $duracao = $profissional?->duracao_padrao_atendimento ?? 30;
                $inicio = Carbon::parse($arguments['inicio']);

                return [
                    'inicio' => $arguments['inicio'],
                    'tipo_atendimento' => 'consulta',
                    'duracao_minutos' => $duracao,
                    'sala_id' => $profissionalId
                        ? app(ServicoConflitoSalaPredefinida::class)->salaPredefinida((int) $profissionalId, $inicio, $inicio->copy()->addMinutes($duracao))
                        : null,
                ];
            })
            ->schema([
                Hidden::make('inicio'),

                Radio::make('tipo_atendimento')
                    ->label('Tipo de atendimento')
                    ->options([
                        'consulta' => 'Consulta',
                        'retorno' => 'Retorno',
                    ])
                    ->inline()
                    ->live()
                    ->visible(fn (): bool => (bool) Profissional::query()->find(data_get($this->data, 'profissional_id'))?->oferece_retorno)
                    ->afterStateUpdated(function (Set $set, ?string $state) {
                        $profissional = Profissional::query()->find(data_get($this->data, 'profissional_id'));

                        $set('duracao_minutos', $state === 'retorno'
                            ? ($profissional?->duracao_retorno_minutos ?? 30)
                            : ($profissional?->duracao_padrao_atendimento ?? 30));
                    }),

                Select::make('duracao_minutos')
                    ->label('Duração')
                    ->options(DuracaoAtendimento::options())
                    ->required()
                    ->live()
                    ->hint(function (Get $get): ?string {
                        $profissional = Profissional::query()->find(data_get($this->data, 'profissional_id'));
                        $duracao = (int) ($get('duracao_minutos') ?: 0);

                        if (! $profissional || ! $duracao || blank($get('inicio'))) {
                            return null;
                        }

                        $inicio = Carbon::parse($get('inicio'));
                        $conflito = $this->conflitoDeHorario($profissional, $inicio, $inicio->copy()->addMinutes($duracao));

                        if (! $conflito) {
                            return null;
                        }

                        return 'Conflita com '.$conflito->paciente?->nome.' ('.$conflito->data_hora_inicio->format('H:i').' às '.$conflito->data_hora_fim->format('H:i').')';
                    })
                    ->hintColor('danger')
                    ->rule(function (Get $get): Closure {
                        return function (string $attribute, $value, Closure $fail) use ($get): void {
                            $profissional = Profissional::query()->find(data_get($this->data, 'profissional_id'));

                            if (! $profissional || blank($get('inicio'))) {
                                return;
                            }

                            $inicio = Carbon::parse($get('inicio'));
                            $conflito = $this->conflitoDeHorario($profissional, $inicio, $inicio->copy()->addMinutes((int) $value));

                            if ($conflito) {
                                $fail('Esse horário conflita com o atendimento de '.$conflito->paciente?->nome.'.');
                            }
                        };
                    }),

                ...$this->camposSalaComAvisoTroca(
                    profissionalId: fn (Get $get): ?int => data_get($this->data, 'profissional_id') ? (int) data_get($this->data, 'profissional_id') : null,
                    inicio: fn (Get $get): ?Carbon => filled($get('inicio')) ? Carbon::parse($get('inicio')) : null,
                    duracaoMinutos: fn (Get $get): int => (int) ($get('duracao_minutos') ?: 30),
                ),

                $this->campoPaciente(),
                $this->campoProcedimentoPrevisto(fn (): ?int => data_get($this->data, 'especialidade_id')),
                $this->campoDescricao(),
            ])
            ->action(function (array $data, array $arguments, Action $action): void {
                $profissionalId = data_get($this->data, 'profissional_id');
                $especialidadeId = data_get($this->data, 'especialidade_id');

                try {
                    app(ServicoCriacaoAgendamento::class)->executar([
                        'paciente_id' => $data['paciente_id'],
                        'profissional_id' => $profissionalId,
                        'especialidade_id' => $especialidadeId,
                        'procedimentos_previstos_ids' => $data['procedimentos_previstos_ids'] ?? [],
                        'sala_id' => $data['sala_id'],
                        'data_hora_inicio' => $arguments['inicio'],
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

                    $action->halt();

                    return;
                }

                Notification::make()->success()->title('Agendamento criado com sucesso.')->send();
            });
    }

    protected function especialidadesDoProfissional(mixed $profissionalId): Collection
    {
        if (! $profissionalId) {
            return collect();
        }

        return Profissional::query()->find($profissionalId)?->especialidades()->pluck('especialidades.id') ?? collect();
    }

    protected function diaSomenteVisualizacao(Carbon $data): bool
    {
        return $data->lt(now()->startOfDay());
    }

    protected function blocosDoDia(): Collection
    {
        $profissionalId = data_get($this->data, 'profissional_id');
        $especialidadeId = data_get($this->data, 'especialidade_id');
        $data = data_get($this->data, 'data');

        if (! $profissionalId || ! $especialidadeId || ! $data) {
            return collect();
        }

        $profissional = Profissional::query()->find($profissionalId);

        if (! $profissional) {
            return collect();
        }

        $dataCarbon = Carbon::parse($data);
        $somenteVisualizacao = $this->diaSomenteVisualizacao($dataCarbon);

        $livres = $somenteVisualizacao
            ? collect()
            : app(ServicoDisponibilidadeAgenda::class)
                ->horariosDisponiveis($profissional, $dataCarbon, (int) $especialidadeId)
                ->map(fn (array $slot): array => [
                    'tipo' => 'livre',
                    'inicio' => $slot['inicio'],
                    'fim' => $slot['fim'],
                ]);

        $ocupados = Agendamento::query()
            ->where('clinica_id', $profissional->clinica_id)
            ->where('profissional_id', $profissional->id)
            ->whereDate('data_hora_inicio', $dataCarbon->toDateString())
            ->whereIn('situacao', [
                SituacaoAgendamento::Agendado,
                SituacaoAgendamento::Confirmado,
                SituacaoAgendamento::Realizado,
            ])
            ->with('paciente')
            ->get()
            ->map(fn (Agendamento $agendamento): array => [
                'tipo' => 'ocupado',
                'id' => $agendamento->id,
                'inicio' => $agendamento->data_hora_inicio,
                'fim' => $agendamento->data_hora_fim,
                'paciente' => $agendamento->paciente?->nome,
                'tipo_atendimento' => $agendamento->tipo_atendimento,
                'ajustavel' => ! $somenteVisualizacao && in_array($agendamento->situacao, [
                    SituacaoAgendamento::Agendado,
                    SituacaoAgendamento::Confirmado,
                ], true),
            ]);

        return $livres->concat($ocupados)
            ->sortBy(fn (array $bloco) => $bloco['inicio']->format('H:i:s'))
            ->values();
    }

    protected function renderAgendaDia(): HtmlString
    {
        return new HtmlString(view('filament.pages.partials.agenda-dia', [
            'blocos' => $this->blocosDoDia(),
        ])->render());
    }

    public function mesAnterior(): void
    {
        $data = Carbon::create($this->anoCalendario, $this->mesCalendario, 1)->subMonthNoOverflow();
        $this->mesCalendario = $data->month;
        $this->anoCalendario = $data->year;
    }

    public function mesProximo(): void
    {
        $data = Carbon::create($this->anoCalendario, $this->mesCalendario, 1)->addMonthNoOverflow();
        $this->mesCalendario = $data->month;
        $this->anoCalendario = $data->year;
    }

    public function selecionarDia(string $dia): void
    {
        $this->data['data'] = $dia;

        $data = Carbon::parse($dia);
        $this->mesCalendario = $data->month;
        $this->anoCalendario = $data->year;
    }

    public function reiniciarCalendarioParaSelecao(): void
    {
        $data = filled(data_get($this->data, 'data')) ? Carbon::parse(data_get($this->data, 'data')) : now();
        $this->mesCalendario = $data->month;
        $this->anoCalendario = $data->year;
    }

    /**
     * @return array<int, array{data: string, dia: int, noMes: bool, disponivel: ?bool, selecionado: bool}>
     */
    protected function diasDoCalendario(): array
    {
        $profissionalId = data_get($this->data, 'profissional_id');
        $especialidadeId = data_get($this->data, 'especialidade_id');
        $diaSelecionado = data_get($this->data, 'data');

        $profissional = $profissionalId ? Profissional::query()->find($profissionalId) : null;
        $servico = $profissional ? app(ServicoDisponibilidadeAgenda::class) : null;

        $inicioMes = Carbon::create($this->anoCalendario, $this->mesCalendario, 1)->startOfMonth();
        $fimMes = $inicioMes->copy()->endOfMonth();
        $inicioGrade = $inicioMes->copy()->startOfWeek(Carbon::SUNDAY);
        $fimGrade = $fimMes->copy()->endOfWeek(Carbon::SATURDAY);

        $hoje = now()->startOfDay();

        $dias = [];
        $cursor = $inicioGrade->copy();

        while ($cursor->lte($fimGrade)) {
            $noMes = $cursor->month === $inicioMes->month && $cursor->year === $inicioMes->year;
            $disponivel = null;
            $somenteVisualizacao = false;

            if ($noMes && $profissional && $especialidadeId) {
                if ($cursor->gte($hoje)) {
                    $disponivel = $servico->horariosDisponiveis($profissional, $cursor->copy(), (int) $especialidadeId)->isNotEmpty();
                } else {
                    $somenteVisualizacao = Agendamento::query()
                        ->where('clinica_id', $profissional->clinica_id)
                        ->where('profissional_id', $profissional->id)
                        ->whereDate('data_hora_inicio', $cursor->toDateString())
                        ->exists();
                }
            }

            $dias[] = [
                'data' => $cursor->toDateString(),
                'dia' => $cursor->day,
                'noMes' => $noMes,
                'disponivel' => $disponivel,
                'somenteVisualizacao' => $somenteVisualizacao,
                'selecionado' => $diaSelecionado === $cursor->toDateString(),
            ];

            $cursor->addDay();
        }

        return $dias;
    }

    protected function renderCalendario(): HtmlString
    {
        return new HtmlString(view('filament.pages.partials.calendario-agendamento', [
            'dias' => $this->diasDoCalendario(),
            'mes' => Carbon::create($this->anoCalendario, $this->mesCalendario, 1),
        ])->render());
    }

    protected function renderSeletorDia(): HtmlString
    {
        return new HtmlString(view('filament.pages.partials.seletor-dia', [
            'calendarioHtml' => $this->renderCalendario(),
            'dataSelecionada' => data_get($this->data, 'data'),
        ])->render());
    }
}
