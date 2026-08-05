<?php

namespace App\Livewire\Pacientes;

use App\Enums\SituacaoAgendamento;
use App\Filament\Support\ResumoAgendamentoSchema;
use App\Models\Agendamento;
use App\Models\HistoricoSituacaoAgendamento;
use App\Models\Paciente;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Reactive;

class HistoricoTabela extends TableWidget
{
    public Paciente $registro;

    #[Reactive]
    public string $aba = 'proximos';

    #[Reactive]
    public ?string $dataInicial = null;

    #[Reactive]
    public ?string $dataFinal = null;

    #[Reactive]
    public ?int $especialidadeId = null;

    #[Reactive]
    public ?int $profissionalId = null;

    #[Reactive]
    public ?int $procedimentoId = null;

    protected function agendamentosQuery(): Builder
    {
        return Agendamento::query()
            ->where('paciente_id', $this->registro->id)
            ->when($this->dataInicial, fn (Builder $q) => $q->whereDate('data_hora_inicio', '>=', $this->dataInicial))
            ->when($this->dataFinal, fn (Builder $q) => $q->whereDate('data_hora_inicio', '<=', $this->dataFinal))
            ->when($this->especialidadeId, fn (Builder $q) => $q->where('especialidade_id', $this->especialidadeId))
            ->when($this->profissionalId, fn (Builder $q) => $q->where('profissional_id', $this->profissionalId))
            ->when(
                $this->procedimentoId,
                fn (Builder $q) => $q->whereHas('procedimentos', fn (Builder $qq) => $qq->whereKey($this->procedimentoId)),
            );
    }

    public function table(Table $table): Table
    {
        return match ($this->aba) {
            'historico' => $table
                ->query(
                    $this->agendamentosQuery()
                        ->where('situacao', SituacaoAgendamento::Realizado)
                        ->with(['registroAtendimento', 'procedimentos'])
                )
                ->columns([
                    TextColumn::make('data_hora_inicio')->label('Data')->formatStateUsing(fn ($state) => $state->translatedFormat('d/m/Y, l, à\s H:i'))->sortable(),
                    TextColumn::make('profissional.nome')->label('Profissional'),
                    TextColumn::make('especialidade.nome')->label('Especialidade'),
                ])
                ->defaultSort('data_hora_inicio', 'desc')
                ->heading(null)
                ->emptyStateHeading('Nenhum atendimento realizado')
                ->recordAction('verDetalhes')
                ->recordActions([$this->verDetalhesAction()]),

            'cancelamentos' => $table
                ->query($this->agendamentosQuery()->where('situacao', SituacaoAgendamento::Cancelado))
                ->columns([
                    TextColumn::make('data_hora_inicio')->label('Data')->dateTime('d/m/Y H:i')->sortable(),
                    TextColumn::make('profissional.nome')->label('Profissional'),
                    TextColumn::make('solicitante_cancelamento')->label('Solicitante')->placeholder('-'),
                    TextColumn::make('motivo_cancelamento')->label('Motivo'),
                ])
                ->defaultSort('cancelado_em', 'desc')
                ->heading(null)
                ->emptyStateHeading('Nenhum cancelamento'),

            'procedimentos' => $table
                ->records(function (): array {
                    $linhas = [];

                    $agendamentos = $this->agendamentosQuery()
                        ->whereHas('procedimentos')
                        ->with('procedimentos')
                        ->orderByDesc('data_hora_inicio')
                        ->get();

                    foreach ($agendamentos as $agendamento) {
                        foreach ($agendamento->procedimentos as $procedimento) {
                            $linhas[] = [
                                'id' => $procedimento->pivot->id,
                                'data' => $agendamento->data_hora_inicio->translatedFormat('d/m/Y, l, à\s H:i'),
                                'procedimento' => $procedimento->nome,
                                'quantidade' => $procedimento->pivot->quantidade,
                                'profissional' => $agendamento->profissional->nome,
                            ];
                        }
                    }

                    return $linhas;
                })
                ->columns([
                    TextColumn::make('data')->label('Data'),
                    TextColumn::make('procedimento')->label('Procedimento'),
                    TextColumn::make('quantidade')->label('Quantidade'),
                    TextColumn::make('profissional')->label('Profissional'),
                ])
                ->heading(null)
                ->emptyStateHeading('Nenhum procedimento realizado'),

            'linha_do_tempo' => $table
                ->query(
                    HistoricoSituacaoAgendamento::query()
                        ->whereHas('agendamento', fn (Builder $q) => $q->where('paciente_id', $this->registro->id))
                        ->with(['agendamento.especialidade', 'agendamento.profissional', 'alteradoPor'])
                )
                ->columns([
                    TextColumn::make('created_at')->label('Quando')->dateTime('d/m/Y H:i'),
                    TextColumn::make('situacao_anterior')->label('De')->placeholder('-'),
                    TextColumn::make('situacao_nova')->label('Para'),
                    TextColumn::make('motivo')->label('Motivo')->placeholder('-'),
                    TextColumn::make('alteradoPor.nome')->label('Alterado por')->placeholder('-'),
                ])
                ->defaultSort('created_at', 'desc')
                ->heading(null)
                ->emptyStateHeading('Nenhum evento registrado'),

            default => $table
                ->query(
                    $this->agendamentosQuery()
                        ->where('data_hora_inicio', '>=', now())
                        ->whereIn('situacao', [SituacaoAgendamento::Agendado, SituacaoAgendamento::Confirmado])
                        ->with(['procedimentosPrevistos'])
                )
                ->columns([
                    TextColumn::make('data_hora_inicio')->label('Data')->formatStateUsing(fn ($state) => $state->translatedFormat('d/m/Y, l, à\s H:i'))->sortable(),
                    TextColumn::make('profissional.nome')->label('Profissional'),
                    TextColumn::make('especialidade.nome')->label('Especialidade'),
                    TextColumn::make('sala.nome')->label('Sala'),
                    TextColumn::make('situacao')->label('Situação')->badge(),
                ])
                ->defaultSort('data_hora_inicio')
                ->heading(null)
                ->emptyStateHeading('Nenhum agendamento futuro')
                ->recordAction('verDetalhes')
                ->recordActions([$this->verDetalhesAction()]),
        };
    }

    protected function verDetalhesAction(): Action
    {
        return Action::make('verDetalhes')
            ->label('Ver detalhes')
            ->icon('heroicon-o-eye')
            ->modalHeading(fn (Agendamento $record): string => 'Resumo da consulta de '.$record->data_hora_inicio->translatedFormat('d/m/Y \à\s H:i'))
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Fechar')
            ->modalWidth('2xl')
            ->schema(function (Agendamento $record): array {
                $record->loadMissing(['paciente', 'profissional', 'especialidade', 'sala', 'procedimentos', 'procedimentosPrevistos', 'registroAtendimento']);

                return ResumoAgendamentoSchema::schema($record);
            });
    }
}
