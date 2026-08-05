<?php

namespace App\Filament\Pages;

use App\Enums\DiaSemana;
use App\Models\Clinica;
use App\Models\HorarioFuncionamento as HorarioFuncionamentoModel;
use Filament\Actions\Action;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use UnitEnum;

class HorarioFuncionamento extends Page
{
    protected static ?string $navigationLabel = 'Horário de funcionamento';

    protected static ?string $title = 'Horário de funcionamento';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clock';

    protected static string|UnitEnum|null $navigationGroup = 'Cadastros';

    protected static ?int $navigationSort = 3;

    protected string $view = 'filament.pages.horario-funcionamento';

    public ?array $data = [];

    public Clinica $clinica;

    public static function canAccess(array $parameters = []): bool
    {
        return (bool) auth()->user()?->clinicaAtivaId();
    }

    public function mount(): void
    {
        $this->clinica = auth()->user()->clinicaAtiva();

        // Idempotente: se a clínica foi criada antes deste recurso existir, garante as 7 linhas.
        HorarioFuncionamentoModel::criarPadraoParaClinica($this->clinica);

        $horarios = HorarioFuncionamentoModel::query()
            ->where('clinica_id', $this->clinica->id)
            ->orderBy('dia_semana')
            ->get();

        $this->form->fill([
            'horarios' => $horarios->map(fn (HorarioFuncionamentoModel $horario): array => [
                'id' => $horario->id,
                'dia_semana' => $horario->dia_semana->value,
                'aberto' => ! $horario->fechado,
                'abre_em' => $horario->abre_em,
                'fecha_em' => $horario->fecha_em,
            ])->all(),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make('Horário semanal')
                ->description('Horário padrão de funcionamento da clínica em cada dia da semana.')
                ->schema([
                    Repeater::make('horarios')
                        ->hiddenLabel()
                        ->addable(false)
                        ->deletable(false)
                        ->reorderable(false)
                        ->schema([
                            Hidden::make('id'),
                            Hidden::make('dia_semana'),
                            Placeholder::make('nome_dia')
                                ->hiddenLabel()
                                ->content(fn (Get $get): string => DiaSemana::from((int) $get('dia_semana'))->getLabel()),
                            Toggle::make('aberto')
                                ->label('Aberto')
                                ->live(),
                            TimePicker::make('abre_em')
                                ->label('Abre')
                                ->seconds(false)
                                ->visible(fn (Get $get): bool => (bool) $get('aberto'))
                                ->required(fn (Get $get): bool => (bool) $get('aberto')),
                            TimePicker::make('fecha_em')
                                ->label('Fecha')
                                ->seconds(false)
                                ->after('abre_em')
                                ->visible(fn (Get $get): bool => (bool) $get('aberto'))
                                ->required(fn (Get $get): bool => (bool) $get('aberto')),
                        ])
                        ->columns(4)
                        ->columnSpanFull(),
                ]),
        ])->statePath('data');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('salvar')
                ->label('Salvar')
                ->action(function (): void {
                    $state = $this->form->getState();

                    foreach ($state['horarios'] as $linha) {
                        HorarioFuncionamentoModel::query()->whereKey($linha['id'])->update([
                            'fechado' => ! $linha['aberto'],
                            'abre_em' => $linha['aberto'] ? $linha['abre_em'] : null,
                            'fecha_em' => $linha['aberto'] ? $linha['fecha_em'] : null,
                        ]);
                    }

                    Notification::make()
                        ->title('Horário semanal salvo')
                        ->success()
                        ->send();

                    $this->redirect(static::getUrl());
                }),
        ];
    }
}
