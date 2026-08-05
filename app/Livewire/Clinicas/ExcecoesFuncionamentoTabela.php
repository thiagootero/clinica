<?php

namespace App\Livewire\Clinicas;

use App\Models\Clinica;
use App\Models\ExcecaoFuncionamento;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class ExcecoesFuncionamentoTabela extends TableWidget
{
    protected string $view = 'livewire.clinicas.excecoes-funcionamento-tabela';

    public Clinica $registro;

    public function mount(Clinica $registro): void
    {
        $this->registro = $registro;
    }

    protected function excecaoForm(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Hidden::make('clinica_id')->default($this->registro->id),
            DatePicker::make('data')
                ->label('Data')
                ->required(),
            Toggle::make('fechado')
                ->label('Fechado o dia todo')
                ->live()
                ->default(true),
            TimePicker::make('abre_em')
                ->label('Abre')
                ->seconds(false)
                ->visible(fn (Get $get): bool => ! $get('fechado'))
                ->required(fn (Get $get): bool => ! $get('fechado'))
                ->dehydrateStateUsing(fn (Get $get, $state) => $get('fechado') ? null : $state),
            TimePicker::make('fecha_em')
                ->label('Fecha')
                ->seconds(false)
                ->after('abre_em')
                ->visible(fn (Get $get): bool => ! $get('fechado'))
                ->required(fn (Get $get): bool => ! $get('fechado'))
                ->dehydrateStateUsing(fn (Get $get, $state) => $get('fechado') ? null : $state),
            TextInput::make('descricao')
                ->label('Descrição')
                ->maxLength(255),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('Feriados e dias específicos')
            ->description('Datas em que a clínica fecha totalmente ou funciona em horário reduzido, sobrepondo o horário semanal.')
            ->query(fn (): Builder => ExcecaoFuncionamento::query()->where('clinica_id', $this->registro->id))
            ->headerActions([
                CreateAction::make()
                    ->label('Adicionar data')
                    ->icon('heroicon-o-calendar-days')
                    ->model(ExcecaoFuncionamento::class)
                    ->schema(fn (Schema $schema): Schema => $this->excecaoForm($schema)),
            ])
            ->columns([
                TextColumn::make('data')
                    ->label('Data')
                    ->date('d/m/Y')
                    ->sortable()
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->where(function (Builder $query) use ($search): void {
                            $query
                                ->whereRaw("DATE_FORMAT(data, '%d/%m/%Y') LIKE ?", ["%{$search}%"])
                                ->orWhere('data', 'like', "%{$search}%");
                        });
                    }),
                TextColumn::make('dia')
                    ->label('Dia')
                    ->state(fn (ExcecaoFuncionamento $record): string => ucfirst($record->data->translatedFormat('l'))),
                TextColumn::make('fechado')
                    ->label('Fechado o dia todo')
                    ->badge()
                    ->formatStateUsing(fn (bool $state): string => $state ? 'Sim' : 'Não')
                    ->color(fn (bool $state): string => $state ? 'danger' : 'success'),
                TextColumn::make('horario')
                    ->label('Horário')
                    ->state(fn (ExcecaoFuncionamento $record): string => $record->fechado
                        ? '—'
                        : substr($record->abre_em, 0, 5).' às '.substr($record->fecha_em, 0, 5)),
                TextColumn::make('descricao')
                    ->label('Descrição')
                    ->searchable()
                    ->limit(30)
                    ->placeholder('—'),
            ])
            ->defaultSort('data', 'asc')
            ->filters([
                SelectFilter::make('fechado')
                    ->label('Situação')
                    ->options([
                        '1' => 'Fechado o dia todo',
                        '0' => 'Horário reduzido',
                    ]),
                SelectFilter::make('ano')
                    ->label('Ano')
                    ->options(fn (): array => array_combine(
                        range(now()->year - 1, now()->year + 2),
                        range(now()->year - 1, now()->year + 2),
                    ))
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when(
                            $data['value'] ?? null,
                            fn (Builder $query, string $value): Builder => $query->whereYear('data', $value),
                        );
                    }),
            ])
            ->recordActions([
                EditAction::make()
                    ->schema(fn (Schema $schema): Schema => $this->excecaoForm($schema)),
                DeleteAction::make(),
            ])
            ->emptyStateHeading('Nenhuma exceção cadastrada')
            ->emptyStateDescription('Crie uma exceção de funcionamento para começar.');
    }
}
