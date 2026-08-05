<?php

namespace App\Filament\Pages;

use App\Enums\SituacaoAgendamento;
use App\Filament\Pages\Concerns\GerenciaAgendamento;
use App\Models\Agendamento;
use Carbon\Carbon;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\TextInput;
use Filament\Pages\Page;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;
use UnitEnum;

class PacientesNaoConfirmados extends Page implements HasTable
{
    use GerenciaAgendamento;
    use InteractsWithTable;

    protected static ?string $navigationLabel = 'Não confirmados';

    protected static ?string $title = 'Pacientes não confirmados';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-bell-alert';

    protected static string|UnitEnum|null $navigationGroup = 'Agenda';

    protected static ?int $navigationSort = 4;

    protected string $view = 'filament.pages.pacientes-nao-confirmados';

    public static function canAccess(array $parameters = []): bool
    {
        return (bool) auth()->user()?->can('viewAny', Agendamento::class);
    }

    /**
     * "Esta semana" olha de hoje até o próximo sábado (inclusive), sem necessariamente completar
     * 7 dias — se hoje é terça, vai até sábado desta mesma semana, não terça que vem.
     */
    protected function proximoSabadoInclusive(Carbon $data): Carbon
    {
        $copia = $data->copy()->startOfDay();
        $diasParaSabado = (6 - $copia->dayOfWeekIso + 7) % 7;

        return $copia->addDays($diasParaSabado)->endOfDay();
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    protected function intervaloParaModo(string $modo, int $dias): array
    {
        $hoje = now()->startOfDay();

        return match ($modo) {
            'dias' => [
                $hoje->copy()->addDays($dias),
                $hoje->copy()->addDays($dias)->endOfDay(),
            ],
            'semana_atual' => [
                $hoje->copy(),
                $this->proximoSabadoInclusive($hoje),
            ],
            'semana_seguinte' => [
                $this->proximoSabadoInclusive($hoje)->copy()->addDays(2)->startOfDay(),
                $this->proximoSabadoInclusive($hoje)->copy()->addDays(7)->endOfDay(),
            ],
            default => [$hoje->copy(), $hoje->copy()->endOfDay()],
        };
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Agendamento::query()
                    ->where('situacao', SituacaoAgendamento::Agendado)
                    ->whereNull('confirmado_em')
                    ->with(['paciente', 'profissional', 'especialidade', 'sala'])
            )
            ->columns([
                TextColumn::make('data_hora_inicio')
                    ->label('Data')
                    ->date('d/m/Y')
                    ->sortable(),
                TextColumn::make('horario')
                    ->label('Horário')
                    ->state(fn (Agendamento $record): string => $record->data_hora_inicio->format('H:i').' – '.$record->data_hora_fim->format('H:i')),
                TextColumn::make('paciente.nome')
                    ->label('Paciente')
                    ->searchable(),
                TextColumn::make('paciente.telefone')
                    ->label('Telefone')
                    ->placeholder('—'),
                TextColumn::make('profissional.nome')
                    ->label('Profissional')
                    ->searchable(),
                TextColumn::make('especialidade.nome')
                    ->label('Especialidade'),
                TextColumn::make('sala.nome')
                    ->label('Sala'),
                TextColumn::make('acoes')
                    ->label('Ações')
                    ->html()
                    ->state(fn (Agendamento $record): HtmlString => $this->acoesRapidasHtml($record)),
            ])
            ->groups([
                Group::make('data_hora_inicio')
                    ->label('Data')
                    ->getKeyFromRecordUsing(fn (Agendamento $record): string => $record->data_hora_inicio->toDateString())
                    ->getTitleFromRecordUsing(fn (Agendamento $record): string => ucfirst($record->data_hora_inicio->translatedFormat('l, j \d\e F \d\e Y')))
                    ->scopeQueryByKeyUsing(fn (Builder $query, ?string $key): Builder => $key ? $query->whereDate('data_hora_inicio', $key) : $query),
            ])
            ->defaultGroup('data_hora_inicio')
            ->defaultSort('data_hora_inicio')
            ->filters([
                Filter::make('periodo')
                    ->form([
                        Radio::make('modo')
                            ->hiddenLabel()
                            ->options([
                                'hoje' => 'Hoje',
                                'dias' => 'Em quantos dias',
                                'semana_atual' => 'Esta semana',
                                'semana_seguinte' => 'Semana que vem',
                            ])
                            ->default('hoje')
                            ->live()
                            ->required(),
                        TextInput::make('dias')
                            ->label('Quantos dias')
                            ->numeric()
                            ->minValue(1)
                            ->default(2)
                            ->visible(fn (Get $get): bool => $get('modo') === 'dias')
                            ->required(fn (Get $get): bool => $get('modo') === 'dias'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        [$inicio, $fim] = $this->intervaloParaModo($data['modo'] ?? 'hoje', (int) ($data['dias'] ?? 1));

                        return $query->whereBetween('data_hora_inicio', [$inicio, $fim]);
                    })
                    ->indicateUsing(function (array $data): ?string {
                        return match ($data['modo'] ?? 'hoje') {
                            'dias' => 'Em '.($data['dias'] ?? 1).' dia(s)',
                            'semana_atual' => 'Esta semana',
                            'semana_seguinte' => 'Semana que vem',
                            default => 'Hoje',
                        };
                    }),
            ])
            ->persistFiltersInSession()
            ->emptyStateHeading('Nenhum paciente pendente de confirmação nesse período');
    }

    /**
     * Mesmo mecanismo já usado nos botões de ação da dashboard (wire:click chamando
     * mountAction diretamente) — evita o problema de encadear mountAction() de dentro do
     * closure de outra Action, que fecha o modal recém-aberto antes de ele aparecer.
     */
    protected function acoesRapidasHtml(Agendamento $record): HtmlString
    {
        return new HtmlString(
            view('filament.pages.partials.acoes-nao-confirmados', ['agendamento' => $record])->render()
        );
    }
}
