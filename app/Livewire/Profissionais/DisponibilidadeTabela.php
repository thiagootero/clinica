<?php

namespace App\Livewire\Profissionais;

use App\Enums\SituacaoAgendamento;
use App\Enums\SituacaoDisponibilidade;
use App\Models\Agendamento;
use App\Models\DisponibilidadeProfissional;
use App\Models\Profissional;
use App\Services\ServicoConflitoSalaPredefinida;
use App\Services\ServicoHorarioFuncionamento;
use Closure;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Enums\GridDirection;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection as SupportCollection;

class DisponibilidadeTabela extends TableWidget
{
    protected string $view = 'livewire.profissionais.disponibilidade-tabela';

    public Profissional $registro;

    public function mount(Profissional $registro): void
    {
        $this->registro = $registro;
    }

    /**
     * @return array<int, string>
     */
    public function mesesDisponiveis(): array
    {
        return [
            1 => 'Janeiro', 2 => 'Fevereiro', 3 => 'Março', 4 => 'Abril',
            5 => 'Maio', 6 => 'Junho', 7 => 'Julho', 8 => 'Agosto',
            9 => 'Setembro', 10 => 'Outubro', 11 => 'Novembro', 12 => 'Dezembro',
        ];
    }

    /**
     * @return array<int, int>
     */
    public function anosDisponiveis(): array
    {
        $anoAtual = now()->year;

        $anoMinimo = DisponibilidadeProfissional::query()
            ->where('profissional_id', $this->registro->id)
            ->min('data_disponibilidade');

        $anoMinimo = $anoMinimo ? Carbon::parse($anoMinimo)->year : $anoAtual;

        $inicio = min($anoMinimo, $anoAtual - 1);
        $fim = $anoAtual + 2;

        return range($inicio, $fim);
    }

    /**
     * @return array<int, string>
     */
    public function diasSemanaDisponiveis(): array
    {
        return [
            1 => 'Segunda', 2 => 'Terça', 3 => 'Quarta', 4 => 'Quinta',
            5 => 'Sexta', 6 => 'Sábado', 7 => 'Domingo',
        ];
    }

    protected function disponibilidadeForm(Schema $schema, ?DisponibilidadeProfissional $record = null): Schema
    {
        return $schema->components([
            Section::make('Disponibilidade')
                ->schema([
                    Hidden::make('profissional_id')->default($this->registro->id),
                    Hidden::make('clinica_id')->default($this->registro->clinica_id),
                    Hidden::make('situacao')->default(SituacaoDisponibilidade::Ativa->value),
                    TextInput::make('profissional_nome')
                        ->label('Profissional')
                        ->default($this->registro->nome)
                        ->disabled()
                        ->dehydrated(false)
                        ->afterStateHydrated(fn (TextInput $component) => $component->state($this->registro->nome)),
                    DatePicker::make('data_disponibilidade')
                        ->label('Data')
                        ->minDate(today())
                        ->live()
                        ->required()
                        ->afterStateUpdated(fn () => $this->resetErrorBag()),
                    TimePicker::make('horario_inicio')
                        ->label('Horário início')
                        ->seconds(false)
                        ->live()
                        ->required()
                        ->afterStateUpdated(fn () => $this->resetErrorBag()),
                    TimePicker::make('horario_fim')
                        ->label('Horário fim')
                        ->seconds(false)
                        ->after('horario_inicio')
                        ->live()
                        ->required()
                        ->afterStateUpdated(fn () => $this->resetErrorBag())
                        ->hint(function (Get $get) use ($record): ?string {
                            return $this->motivoSobreposicaoCache($get('data_disponibilidade'), $get('horario_inicio'), $get('horario_fim'), $record?->id)
                                ?? $this->motivoForaDoHorarioCache($get('data_disponibilidade'), $get('horario_inicio'), $get('horario_fim'));
                        })
                        ->hintColor('danger')
                        ->rule(function (Get $get) use ($record): Closure {
                            return function (string $attribute, $value, Closure $fail) use ($get, $record): void {
                                $sobreposicao = $this->motivoSobreposicaoCache($get('data_disponibilidade'), $get('horario_inicio'), $value, $record?->id);

                                if ($sobreposicao) {
                                    $fail($sobreposicao);

                                    return;
                                }

                                $motivo = $this->motivoForaDoHorarioCache($get('data_disponibilidade'), $get('horario_inicio'), $value);

                                if ($motivo) {
                                    $fail($motivo);
                                }
                            };
                        }),
                    Select::make('sala_id')
                        ->label('Sala (opcional)')
                        ->live()
                        ->options(fn (): array => $this->registro->salas()->wherePivot('ativo', true)->pluck('salas.nome', 'salas.id')->all())
                        ->afterStateUpdated(fn () => $this->resetErrorBag())
                        ->helperText('Deixe em branco para escolher a sala no momento do agendamento. Se definida, essa sala fica reservada para este profissional nesse horário.')
                        ->hint(function (Get $get) use ($record): ?string {
                            $conflitos = $this->conflitosSobrepostosCache($get('sala_id'), $get('data_disponibilidade'), $get('horario_inicio'), $get('horario_fim'), $record?->id);

                            if ($conflitos->isEmpty()) {
                                return null;
                            }

                            $nomes = $conflitos->pluck('profissional.nome')->unique()->implode(', ');

                            return "Sala já predefinida para {$nomes} nesse horário.";
                        })
                        ->hintColor('danger')
                        ->rule(function (Get $get) use ($record): Closure {
                            return function (string $attribute, $value, Closure $fail) use ($get, $record): void {
                                if (blank($value)) {
                                    return;
                                }

                                $conflitos = $this->conflitosSobrepostosCache($value, $get('data_disponibilidade'), $get('horario_inicio'), $get('horario_fim'), $record?->id);

                                if ($conflitos->isNotEmpty()) {
                                    $nomes = $conflitos->pluck('profissional.nome')->unique()->implode(', ');

                                    $fail("Esta sala já está predefinida para {$nomes} nesse horário. Escolha outra sala ou ajuste o horário.");
                                }
                            };
                        }),
                    Toggle::make('tem_intervalo')
                        ->label('Com intervalo')
                        ->live()
                        ->dehydrated(false)
                        ->afterStateHydrated(function (Toggle $component, $state, $record): void {
                            if ($record) {
                                $component->state(filled($record->intervalo_inicio));
                            }
                        }),
                    TimePicker::make('intervalo_inicio')
                        ->label('Intervalo início')
                        ->seconds(false)
                        ->visible(fn (Get $get): bool => (bool) $get('tem_intervalo'))
                        ->required(fn (Get $get): bool => (bool) $get('tem_intervalo'))
                        ->dehydratedWhenHidden()
                        ->dehydrateStateUsing(fn (Get $get, $state) => $get('tem_intervalo') ? $state : null),
                    TimePicker::make('intervalo_fim')
                        ->label('Intervalo fim')
                        ->seconds(false)
                        ->after('intervalo_inicio')
                        ->visible(fn (Get $get): bool => (bool) $get('tem_intervalo'))
                        ->required(fn (Get $get): bool => (bool) $get('tem_intervalo'))
                        ->dehydratedWhenHidden()
                        ->dehydrateStateUsing(fn (Get $get, $state) => $get('tem_intervalo') ? $state : null),
                ]),
        ])->columns(1);
    }

    protected function multiplosForm(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make('Como deseja marcar as datas?')
                ->schema([
                    Radio::make('modo')
                        ->hiddenLabel()
                        ->options([
                            'dias_semana' => 'Repetir por dia(s) da semana, dentro de um período',
                            'datas_manuais' => 'Selecionar datas específicas manualmente',
                        ])
                        ->live()
                        ->required()
                        ->afterStateUpdated(function (Set $set): void {
                            $set('data_inicial', null);
                            $set('data_final', null);
                            $set('dias_semana', []);
                            $set('datas_manuais', []);
                        }),
                ]),
            Section::make('Período e dias da semana')
                ->visible(fn (Get $get): bool => $get('modo') === 'dias_semana')
                ->schema([
                    DatePicker::make('data_inicial')
                        ->label('Data inicial')
                        ->minDate(today())
                        ->live()
                        ->required(fn (Get $get): bool => $get('modo') === 'dias_semana'),
                    DatePicker::make('data_final')
                        ->label('Data final')
                        ->afterOrEqual('data_inicial')
                        ->helperText('Período máximo de 1 ano.')
                        ->live()
                        ->required(fn (Get $get): bool => $get('modo') === 'dias_semana')
                        ->rule(function (Get $get): \Closure {
                            return function (string $attribute, $value, \Closure $fail) use ($get): void {
                                if (blank($get('data_inicial')) || blank($value)) {
                                    return;
                                }

                                if (Carbon::parse($get('data_inicial'))->diffInDays(Carbon::parse($value)) > 366) {
                                    $fail('O período máximo permitido é de 1 ano.');
                                }
                            };
                        }),
                    CheckboxList::make('dias_semana')
                        ->label('Dias da semana')
                        ->options([
                            1 => 'Segunda',
                            2 => 'Terça',
                            3 => 'Quarta',
                            4 => 'Quinta',
                            5 => 'Sexta',
                            6 => 'Sábado',
                            7 => 'Domingo',
                        ])
                        ->columns(['default' => 4])
                        ->gridDirection(GridDirection::Row)
                        ->live()
                        ->required(fn (Get $get): bool => $get('modo') === 'dias_semana'),
                ]),
            Section::make('Datas específicas')
                ->visible(fn (Get $get): bool => $get('modo') === 'datas_manuais')
                ->schema([
                    Repeater::make('datas_manuais')
                        ->label('Datas')
                        ->addActionLabel('Adicionar data')
                        ->live()
                        ->schema([
                            DatePicker::make('data')->label('Data')->minDate(today())->required()->live(),
                        ])
                        ->minItems(1)
                        ->required(fn (Get $get): bool => $get('modo') === 'datas_manuais'),
                ]),
            Section::make('Datas já cadastradas')
                ->visible(fn (Get $get): bool => count($this->conflitosPara($this->resolverDatasFromGet($get), $get('horario_inicio'), $get('horario_fim'))) > 0)
                ->schema([
                    Placeholder::make('aviso_conflitos')
                        ->hiddenLabel()
                        ->content(function (Get $get): string {
                            $lista = collect($this->conflitosPara($this->resolverDatasFromGet($get), $get('horario_inicio'), $get('horario_fim')))
                                ->map(fn (string $data): string => Carbon::parse($data)->format('d/m/Y'))
                                ->implode(', ');

                            return "Este(a) profissional já possui disponibilidade cadastrada nesse horário em: {$lista}.";
                        }),
                    Radio::make('resolucao_conflito')
                        ->label('O que deseja fazer com essas datas?')
                        ->options([
                            'manter' => 'Manter os horários já cadastrados nessas datas (pular estas datas)',
                            'substituir' => 'Substituir: excluir os horários existentes nessas datas e criar os novos',
                        ])
                        ->default('manter')
                        ->required(),
                ]),
            Section::make('Horário')
                ->visible(fn (Get $get): bool => filled($get('modo')))
                ->schema([
                    TimePicker::make('horario_inicio')
                        ->label('Horário início')
                        ->seconds(false)
                        ->live()
                        ->required()
                        ->afterStateUpdated(fn () => $this->resetErrorBag()),
                    TimePicker::make('horario_fim')
                        ->label('Horário fim')
                        ->seconds(false)
                        ->after('horario_inicio')
                        ->live()
                        ->required()
                        ->afterStateUpdated(fn () => $this->resetErrorBag())
                        ->rule(function (Get $get): Closure {
                            return function (string $attribute, $value, Closure $fail) use ($get): void {
                                $datas = $this->datasForaDoHorario($get);

                                if (filled($datas)) {
                                    $lista = collect($datas)
                                        ->map(fn (string $data): string => Carbon::parse($data)->format('d/m/Y'))
                                        ->implode(', ');

                                    $fail("Fora do horário de funcionamento da clínica em: {$lista}. Ajuste o horário ou remova essas datas antes de salvar.");
                                }
                            };
                        }),
                    Select::make('sala_id')
                        ->label('Sala (opcional)')
                        ->live()
                        ->options(fn (): array => $this->registro->salas()->wherePivot('ativo', true)->pluck('salas.nome', 'salas.id')->all())
                        ->afterStateUpdated(fn () => $this->resetErrorBag())
                        ->helperText('Deixe em branco para escolher a sala no momento do agendamento. Se definida, essa sala fica reservada para este profissional nesses horários.')
                        ->rule(function (Get $get): Closure {
                            return function (string $attribute, $value, Closure $fail) use ($get): void {
                                if (blank($value)) {
                                    return;
                                }

                                $datas = $this->datasComConflitoSobreposto($get);

                                if (filled($datas)) {
                                    $lista = collect($datas)
                                        ->map(fn (string $data): string => Carbon::parse($data)->format('d/m/Y'))
                                        ->implode(', ');

                                    $fail("Esta sala já está predefinida para outro profissional nesse mesmo horário em: {$lista}. Ajuste a sala, o horário ou remova essas datas antes de salvar.");
                                }
                            };
                        }),
                    Toggle::make('tem_intervalo')->label('Com intervalo')->live(),
                    TimePicker::make('intervalo_inicio')
                        ->label('Intervalo início')
                        ->seconds(false)
                        ->visible(fn (Get $get): bool => (bool) $get('tem_intervalo'))
                        ->required(fn (Get $get): bool => (bool) $get('tem_intervalo')),
                    TimePicker::make('intervalo_fim')
                        ->label('Intervalo fim')
                        ->seconds(false)
                        ->after('intervalo_inicio')
                        ->visible(fn (Get $get): bool => (bool) $get('tem_intervalo'))
                        ->required(fn (Get $get): bool => (bool) $get('tem_intervalo')),
                ]),
            Section::make('Conflitos de sala')
                ->visible(fn (Get $get): bool => filled($get('sala_id')) && filled($this->datasComConflitoSobreposto($get)))
                ->schema([
                    Placeholder::make('aviso_conflito_sobreposto')
                        ->hiddenLabel()
                        ->content(function (Get $get): string {
                            $lista = collect($this->datasComConflitoSobreposto($get))
                                ->map(fn (string $data): string => Carbon::parse($data)->format('d/m/Y'))
                                ->implode(', ');

                            return "Não será possível salvar: já existe outro profissional com esta sala nesse mesmo horário em {$lista}. Ajuste a sala, o horário ou remova essas datas.";
                        }),
                ]),
            Section::make('Fora do horário de funcionamento')
                ->visible(fn (Get $get): bool => filled($this->datasForaDoHorario($get)))
                ->schema([
                    Placeholder::make('aviso_fora_do_horario')
                        ->hiddenLabel()
                        ->content(function (Get $get): string {
                            $lista = collect($this->datasForaDoHorario($get))
                                ->map(fn (string $data): string => Carbon::parse($data)->format('d/m/Y'))
                                ->implode(', ');

                            return "Não será possível salvar: fora do horário de funcionamento da clínica em {$lista}. Ajuste o horário ou remova essas datas.";
                        }),
                ]),
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<int, string>
     */
    protected function resolverDatas(array $data): array
    {
        if ($data['modo'] === 'datas_manuais') {
            return $this->datasDoRepeater($data['datas_manuais'] ?? []);
        }

        return $this->datasPorDiasSemana($data['data_inicial'] ?? null, $data['data_final'] ?? null, $data['dias_semana'] ?? []);
    }

    /**
     * @return array<int, string>
     */
    protected function resolverDatasFromGet(Get $get): array
    {
        if ($get('modo') === 'datas_manuais') {
            return $this->datasDoRepeater($get('datas_manuais') ?? []);
        }

        if ($get('modo') === 'dias_semana') {
            return $this->datasPorDiasSemana($get('data_inicial'), $get('data_final'), $get('dias_semana') ?? []);
        }

        return [];
    }

    /**
     * @param  array<int, array<string, mixed>>  $itens
     * @return array<int, string>
     */
    protected function datasDoRepeater(array $itens): array
    {
        return collect($itens)
            ->pluck('data')
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  array<int, mixed>  $dias
     * @return array<int, string>
     */
    protected function datasPorDiasSemana(?string $inicio, ?string $fim, array $dias): array
    {
        if (blank($inicio) || blank($fim) || empty($dias)) {
            return [];
        }

        try {
            $inicio = Carbon::parse($inicio);
            $fim = Carbon::parse($fim);
        } catch (\Throwable) {
            return [];
        }

        if ($inicio->gt($fim) || $inicio->diffInDays($fim) > 366) {
            return [];
        }

        $diasSelecionados = array_map('intval', $dias);

        $datas = [];
        $cursor = $inicio->copy();

        while ($cursor->lte($fim)) {
            if (in_array($cursor->isoWeekday(), $diasSelecionados, true)) {
                $datas[] = $cursor->toDateString();
            }

            $cursor->addDay();
        }

        return $datas;
    }

    /**
     * Entre as datas informadas, quais já têm uma disponibilidade do profissional que sobrepõe o
     * horário dado (dois blocos no mesmo dia sem sobreposição não são conflito).
     *
     * @param  array<int, string>  $datas
     * @return array<int, string>
     */
    protected function conflitosPara(array $datas, ?string $horarioInicio, ?string $horarioFim): array
    {
        if (empty($datas) || blank($horarioInicio) || blank($horarioFim)) {
            return [];
        }

        $datasOrdenadas = collect($datas)->sort();

        return DisponibilidadeProfissional::query()
            ->where('profissional_id', $this->registro->id)
            ->whereDate('data_disponibilidade', '>=', $datasOrdenadas->first())
            ->whereDate('data_disponibilidade', '<=', $datasOrdenadas->last())
            ->where('horario_inicio', '<', $horarioFim)
            ->where('horario_fim', '>', $horarioInicio)
            ->pluck('data_disponibilidade')
            ->map(fn ($data): string => $data->toDateString())
            ->filter(fn (string $data): bool => in_array($data, $datas, true))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @var array<string, SupportCollection>
     */
    protected array $cacheConflitosSala = [];

    protected function servicoConflitoSala(): ServicoConflitoSalaPredefinida
    {
        return app(ServicoConflitoSalaPredefinida::class);
    }

    protected function conflitosSobrepostosCache(?string $salaId, ?string $data, ?string $horarioInicio, ?string $horarioFim, ?int $ignorarId): SupportCollection
    {
        if (blank($salaId) || blank($data) || blank($horarioInicio) || blank($horarioFim)) {
            return collect();
        }

        $chave = 'sobreposto|'.implode('|', [$salaId, $data, $horarioInicio, $horarioFim, $ignorarId]);

        return $this->cacheConflitosSala[$chave] ??= $this->servicoConflitoSala()->conflitosSobrepostos(
            (int) $salaId,
            $data,
            $horarioInicio,
            $horarioFim,
            $this->registro->id,
            $ignorarId,
        );
    }

    /**
     * @var array<string, ?string>
     */
    protected array $cacheMotivoSobreposicao = [];

    /**
     * Uma disponibilidade só é conflito com outra do mesmo profissional se os horários se
     * sobrepõem — dois blocos no mesmo dia sem sobreposição (ex.: 08h-12h e 14h-18h) são uma
     * agenda quebrada válida, não um conflito.
     */
    protected function motivoSobreposicaoCache(?string $data, ?string $horarioInicio, ?string $horarioFim, ?int $ignorarId): ?string
    {
        if (blank($data) || blank($horarioInicio) || blank($horarioFim)) {
            return null;
        }

        $chave = implode('|', [$data, $horarioInicio, $horarioFim, $ignorarId]);

        if (array_key_exists($chave, $this->cacheMotivoSobreposicao)) {
            return $this->cacheMotivoSobreposicao[$chave];
        }

        $existe = DisponibilidadeProfissional::query()
            ->where('profissional_id', $this->registro->id)
            ->where('data_disponibilidade', $data)
            ->where('horario_inicio', '<', $horarioFim)
            ->where('horario_fim', '>', $horarioInicio)
            ->when($ignorarId, fn ($query) => $query->whereKeyNot($ignorarId))
            ->exists();

        return $this->cacheMotivoSobreposicao[$chave] = $existe
            ? 'Já existe uma disponibilidade cadastrada nesse horário para esta data.'
            : null;
    }

    /**
     * @var array<string, ?string>
     */
    protected array $cacheMotivoForaDoHorario = [];

    protected function servicoHorarioFuncionamento(): ServicoHorarioFuncionamento
    {
        return app(ServicoHorarioFuncionamento::class);
    }

    protected function motivoForaDoHorarioCache(?string $data, ?string $horarioInicio, ?string $horarioFim): ?string
    {
        if (blank($data) || blank($horarioInicio) || blank($horarioFim)) {
            return null;
        }

        $chave = implode('|', [$data, $horarioInicio, $horarioFim]);

        if (array_key_exists($chave, $this->cacheMotivoForaDoHorario)) {
            return $this->cacheMotivoForaDoHorario[$chave];
        }

        return $this->cacheMotivoForaDoHorario[$chave] = $this->servicoHorarioFuncionamento()->motivoForaDoHorario(
            $this->registro->clinica,
            Carbon::parse($data.' '.$horarioInicio),
            Carbon::parse($data.' '.$horarioFim),
        );
    }

    /**
     * @var array<string, array<int, string>>
     */
    protected array $cacheClassificacaoDatas = [];

    /**
     * Entre as datas informadas, quais têm conflito de sala por sobreposição de horário com
     * outro profissional (nunca permitido — a sala sempre é excluída dessas datas).
     *
     * @param  array<int, string>  $datas
     * @return array<int, string>
     */
    protected function classificarConflitosDatas(?string $salaId, ?string $horarioInicio, ?string $horarioFim, array $datas): array
    {
        if (blank($salaId) || empty($datas) || blank($horarioInicio) || blank($horarioFim)) {
            return [];
        }

        $chave = implode('|', [$salaId, $horarioInicio, $horarioFim, md5(implode(',', $datas))]);

        if (isset($this->cacheClassificacaoDatas[$chave])) {
            return $this->cacheClassificacaoDatas[$chave];
        }

        $datasOrdenadas = collect($datas)->sort();

        $registros = DisponibilidadeProfissional::query()
            ->where('sala_id', $salaId)
            ->whereDate('data_disponibilidade', '>=', $datasOrdenadas->first())
            ->whereDate('data_disponibilidade', '<=', $datasOrdenadas->last())
            ->where('situacao', SituacaoDisponibilidade::Ativa)
            ->where('profissional_id', '!=', $this->registro->id)
            ->get(['data_disponibilidade', 'horario_inicio', 'horario_fim'])
            ->filter(fn (DisponibilidadeProfissional $r): bool => in_array($r->data_disponibilidade->toDateString(), $datas, true));

        $sobreposto = [];

        foreach ($datas as $data) {
            $temSobreposicao = $registros
                ->filter(fn (DisponibilidadeProfissional $r): bool => $r->data_disponibilidade->toDateString() === $data)
                ->contains(fn (DisponibilidadeProfissional $r): bool => $r->horario_inicio < $horarioFim && $r->horario_fim > $horarioInicio);

            if ($temSobreposicao) {
                $sobreposto[] = $data;
            }
        }

        return $this->cacheClassificacaoDatas[$chave] = $sobreposto;
    }

    /**
     * @return array<int, string>
     */
    protected function datasComConflitoSobreposto(Get $get): array
    {
        return $this->classificarConflitosDatas($get('sala_id'), $get('horario_inicio'), $get('horario_fim'), $this->resolverDatasFromGet($get));
    }

    /**
     * Entre as datas resolvidas no formulário, quais ficam fora do horário de funcionamento da
     * clínica (feriado, dia sem expediente ou fora da janela abre/fecha) para o horário escolhido.
     *
     * @return array<int, string>
     */
    protected function datasForaDoHorario(Get $get): array
    {
        $horarioInicio = $get('horario_inicio');
        $horarioFim = $get('horario_fim');

        if (blank($horarioInicio) || blank($horarioFim)) {
            return [];
        }

        return collect($this->resolverDatasFromGet($get))
            ->filter(fn (string $data): bool => filled($this->motivoForaDoHorarioCache($data, $horarioInicio, $horarioFim)))
            ->values()
            ->all();
    }

    /**
     * @param  array<int, string>  $datas
     * @param  array<string, mixed>  $horario
     */
    protected function criarDisponibilidades(array $datas, array $horario, ?int $salaId = null): int
    {
        $criados = 0;

        foreach ($datas as $dataDisponibilidade) {
            DisponibilidadeProfissional::create([
                'clinica_id' => $this->registro->clinica_id,
                'profissional_id' => $this->registro->id,
                'sala_id' => $salaId,
                'data_disponibilidade' => $dataDisponibilidade,
                'horario_inicio' => $horario['horario_inicio'],
                'horario_fim' => $horario['horario_fim'],
                'intervalo_inicio' => $horario['intervalo_inicio'],
                'intervalo_fim' => $horario['intervalo_fim'],
                'situacao' => SituacaoDisponibilidade::Ativa->value,
            ]);

            $criados++;
        }

        return $criados;
    }

    protected function possuiAgendamento(DisponibilidadeProfissional $disponibilidade): bool
    {
        $inicio = Carbon::parse($disponibilidade->data_disponibilidade->toDateString().' '.$disponibilidade->horario_inicio);
        $fim = Carbon::parse($disponibilidade->data_disponibilidade->toDateString().' '.$disponibilidade->horario_fim);

        return Agendamento::query()
            ->where('profissional_id', $disponibilidade->profissional_id)
            ->whereIn('situacao', [
                SituacaoAgendamento::Agendado->value,
                SituacaoAgendamento::Confirmado->value,
                SituacaoAgendamento::Realizado->value,
            ])
            ->where('data_hora_inicio', '<', $fim)
            ->where('data_hora_fim', '>', $inicio)
            ->exists();
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading(null)
            ->query(fn (): Builder => DisponibilidadeProfissional::query()->where('profissional_id', $this->registro->id))
            ->headerActions([
                CreateAction::make()
                    ->label('Adicionar horário')
                    ->icon('heroicon-o-clock')
                    ->model(DisponibilidadeProfissional::class)
                    ->schema(fn (Schema $schema): Schema => $this->disponibilidadeForm($schema)),
                Action::make('adicionarMultiplos')
                    ->label('Adicionar Múltiplos Horários')
                    ->icon('heroicon-o-calendar-days')
                    ->modalHeading('Adicionar múltiplos horários')
                    ->modalWidth('2xl')
                    ->schema(fn (Schema $schema): Schema => $this->multiplosForm($schema))
                    ->action(function (array $data): void {
                        $datas = $this->resolverDatas($data);

                        if (count($datas) > 500) {
                            Notification::make()
                                ->title('Muitas datas de uma vez')
                                ->body('O período e os dias selecionados geram mais de 500 datas. Reduza o período ou os dias da semana.')
                                ->danger()
                                ->send();

                            return;
                        }

                        $horario = [
                            'horario_inicio' => $data['horario_inicio'],
                            'horario_fim' => $data['horario_fim'],
                            'intervalo_inicio' => $data['tem_intervalo'] ? $data['intervalo_inicio'] : null,
                            'intervalo_fim' => $data['tem_intervalo'] ? $data['intervalo_fim'] : null,
                        ];

                        $conflitos = $this->conflitosPara($datas, $data['horario_inicio'], $data['horario_fim']);

                        if (! empty($conflitos)) {
                            if (($data['resolucao_conflito'] ?? 'manter') === 'manter') {
                                $datas = array_values(array_diff($datas, $conflitos));
                            } else {
                                DisponibilidadeProfissional::query()
                                    ->where('profissional_id', $this->registro->id)
                                    ->whereIn('data_disponibilidade', $conflitos)
                                    ->delete();
                            }
                        }

                        $salaId = $data['sala_id'] ?? null;

                        $criados = $this->criarDisponibilidades($datas, $horario, filled($salaId) ? (int) $salaId : null);

                        Notification::make()
                            ->title('Horários gerados')
                            ->body("{$criados} horário(s) criado(s).")
                            ->success()
                            ->send();
                    }),
            ])
            ->columns([
                TextColumn::make('data_disponibilidade')
                    ->label('Data')
                    ->date('d/m/Y')
                    ->sortable()
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->where(function (Builder $query) use ($search): void {
                            $query
                                ->whereRaw("DATE_FORMAT(data_disponibilidade, '%d/%m/%Y') LIKE ?", ["%{$search}%"])
                                ->orWhere('data_disponibilidade', 'like', "%{$search}%");
                        });
                    }),
                TextColumn::make('dia')
                    ->label('Dia')
                    ->state(fn (DisponibilidadeProfissional $record): string => ucfirst($record->data_disponibilidade->translatedFormat('l'))),
                TextColumn::make('horario')
                    ->label('Horário')
                    ->state(fn (DisponibilidadeProfissional $record): string => substr($record->horario_inicio, 0, 5).' às '.substr($record->horario_fim, 0, 5))
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->where(function (Builder $query) use ($search): void {
                            $query
                                ->where('horario_inicio', 'like', "%{$search}%")
                                ->orWhere('horario_fim', 'like', "%{$search}%");
                        });
                    }),
                TextColumn::make('intervalo')
                    ->label('Intervalo')
                    ->state(fn (DisponibilidadeProfissional $record): string => $record->intervalo_inicio
                        ? substr($record->intervalo_inicio, 0, 5).' às '.substr($record->intervalo_fim, 0, 5)
                        : 'Sem intervalo'),
            ])
            ->defaultSort('data_disponibilidade', 'asc')
            ->filters([
                SelectFilter::make('mes')
                    ->label('Mês')
                    ->options($this->mesesDisponiveis())
                    ->default((string) now()->month)
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when(
                            $data['value'] ?? null,
                            fn (Builder $query, string $value): Builder => $query->whereMonth('data_disponibilidade', $value),
                        );
                    }),
                SelectFilter::make('ano')
                    ->label('Ano')
                    ->options(fn (): array => array_combine($this->anosDisponiveis(), $this->anosDisponiveis()))
                    ->default((string) now()->year)
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when(
                            $data['value'] ?? null,
                            fn (Builder $query, string $value): Builder => $query->whereYear('data_disponibilidade', $value),
                        );
                    }),
                SelectFilter::make('dia_semana')
                    ->label('Dia da semana')
                    ->options($this->diasSemanaDisponiveis())
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when(
                            $data['value'] ?? null,
                            fn (Builder $query, string $value): Builder => $query->whereRaw('DAYOFWEEK(data_disponibilidade) = ?', [((int) $value % 7) + 1]),
                        );
                    }),
            ])
            ->recordActions([
                EditAction::make()->schema(fn (Schema $schema, ?DisponibilidadeProfissional $record): Schema => $this->disponibilidadeForm($schema, $record)),
                DeleteAction::make()
                    ->before(function (DisponibilidadeProfissional $record, Action $action): void {
                        if ($this->possuiAgendamento($record)) {
                            Notification::make()
                                ->title('Não é possível excluir')
                                ->body('Há paciente agendado nesta data. Remarque o agendamento antes de excluir o horário.')
                                ->danger()
                                ->send();

                            $action->cancel();
                        }
                    }),
            ])
            ->bulkActions([
                BulkAction::make('excluirEmMassa')
                    ->label('Excluir selecionados')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(function (Collection $records): void {
                        $excluidos = 0;
                        $bloqueados = 0;

                        foreach ($records as $record) {
                            if ($this->possuiAgendamento($record)) {
                                $bloqueados++;

                                continue;
                            }

                            $record->delete();
                            $excluidos++;
                        }

                        $notificacao = Notification::make()
                            ->title('Exclusão em massa')
                            ->body("{$excluidos} horário(s) excluído(s)".($bloqueados ? ", {$bloqueados} bloqueado(s) por ter paciente agendado" : '').'.');

                        $bloqueados > 0 ? $notificacao->warning() : $notificacao->success();

                        $notificacao->send();
                    })
                    ->deselectRecordsAfterCompletion(),
            ])
            ->emptyStateHeading('Nenhuma disponibilidade cadastrada');
    }
}
