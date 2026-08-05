<?php

namespace App\Filament\Pages;

use App\Enums\SituacaoAgendamento;
use App\Filament\Support\ResumoAgendamentoSchema;
use App\Models\Agendamento;
use App\Models\Profissional;
use App\Models\Sala;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Pages\Page;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class AgendaPorSalaProfissional extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationLabel = 'Sala x Profissional';

    protected static ?string $title = 'Sala x Profissional';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-adjustments-horizontal';

    protected static string|UnitEnum|null $navigationGroup = 'Agenda';

    protected static ?int $navigationSort = 5;

    protected string $view = 'filament.pages.agenda-por-sala-profissional';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill();
    }

    public static function canAccess(array $parameters = []): bool
    {
        return (bool) auth()->user()?->can('viewAny', Agendamento::class);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Select::make('profissional_id')
                    ->label('Profissional')
                    ->options(fn (): array => Profissional::query()->daClinica()->where('ativo', true)->orderBy('nome')->pluck('nome', 'id')->all())
                    ->searchable()
                    ->live()
                    ->afterStateUpdated(fn (Set $set) => $set('sala_id', null)),
                Select::make('sala_id')
                    ->label('Sala')
                    ->options(fn (Get $get): array => filled($get('profissional_id'))
                        ? Sala::query()->whereRelation('profissionais', 'profissionais.id', $get('profissional_id'))->orderBy('nome')->pluck('nome', 'id')->all()
                        : [])
                    ->searchable()
                    ->live()
                    ->disabled(fn (Get $get): bool => blank($get('profissional_id')))
                    ->helperText(fn (Get $get): ?string => blank($get('profissional_id')) ? 'Selecione um profissional primeiro.' : null),
            ])
            ->statePath('data');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(function (): Builder {
                $profissionalId = $this->data['profissional_id'] ?? null;
                $salaId = $this->data['sala_id'] ?? null;

                if (! $profissionalId || ! $salaId) {
                    return Agendamento::query()->whereRaw('1 = 0');
                }

                return Agendamento::query()
                    ->where('profissional_id', $profissionalId)
                    ->where('sala_id', $salaId)
                    ->where('situacao', '!=', SituacaoAgendamento::Cancelado)
                    ->with(['paciente', 'especialidade']);
            })
            ->columns([
                TextColumn::make('data_hora_inicio')
                    ->label('Data')
                    ->formatStateUsing(fn ($state) => $state->translatedFormat('d/m/Y, l, à\s H:i'))
                    ->sortable(),
                TextColumn::make('paciente.nome')->label('Paciente')->searchable(),
                TextColumn::make('especialidade.nome')->label('Especialidade'),
                TextColumn::make('situacao')->label('Situação')->badge(),
            ])
            ->recordActions([
                Action::make('resumo')
                    ->label('Ver resumo')
                    ->icon('heroicon-o-eye')
                    ->color('gray')
                    ->modalHeading(fn (Agendamento $record): string => 'Resumo da consulta de '.($record->paciente?->nome ?? ''))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Fechar')
                    ->modalWidth('2xl')
                    ->schema(function (Agendamento $record): array {
                        $record->loadMissing(['paciente', 'profissional', 'especialidade', 'sala', 'procedimentos', 'procedimentosPrevistos', 'registroAtendimento']);

                        return ResumoAgendamentoSchema::schema($record);
                    }),
            ])
            ->defaultSort('data_hora_inicio')
            ->emptyStateHeading(fn (): string => (filled($this->data['profissional_id'] ?? null) && filled($this->data['sala_id'] ?? null))
                ? 'Nenhum agendamento encontrado para esse profissional nessa sala'
                : 'Selecione um profissional e uma sala para ver os agendamentos');
    }
}
