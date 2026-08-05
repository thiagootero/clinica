<?php

namespace App\Livewire\Salas;

use App\Enums\SituacaoAgendamento;
use App\Filament\Support\ResumoAgendamentoSchema;
use App\Models\Agendamento;
use App\Services\ServicoIntervaloAgenda;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Livewire\Attributes\Reactive;

class AgendaTabela extends TableWidget
{
    protected const HORARIO_INICIO_GRADE = '08:00';

    protected const HORARIO_FIM_GRADE = '18:00';

    protected const PASSO_MINUTOS = 15;

    #[Reactive]
    public ?int $salaId = null;

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
                TextColumn::make('profissional')->label('Profissional')->placeholder('—'),
                TextColumn::make('especialidade')->label('Especialidade')->placeholder('—'),
                TextColumn::make('situacao')
                    ->label('Situação')
                    ->badge()
                    ->state(fn (array $record) => $record['tipo'] === 'livre' ? 'Livre' : $record['situacao']->getLabel())
                    ->color(fn (array $record) => $record['tipo'] === 'livre' ? 'success' : null),
            ])
            ->recordActions([
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
            ])
            ->defaultGroup(
                Group::make('data')
                    ->getTitleFromRecordUsing(fn (array $record) => $record['inicio']->translatedFormat('d/m/Y (l)'))
            )
            ->groupingSettingsHidden()
            ->heading(null)
            ->paginated(false)
            ->emptyStateHeading('Nenhum horário no período selecionado');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function montarLinhas(): array
    {
        if (! $this->salaId) {
            return [];
        }

        [$inicio, $fim] = app(ServicoIntervaloAgenda::class)->intervalo(Carbon::parse($this->data), $this->visao);

        $ocupados = Agendamento::query()
            ->where('sala_id', $this->salaId)
            ->whereBetween('data_hora_inicio', [$inicio, $fim])
            ->whereNotIn('situacao', [SituacaoAgendamento::Cancelado, SituacaoAgendamento::Remarcado])
            ->with(['paciente', 'profissional', 'especialidade'])
            ->get();

        $linhas = collect();

        foreach ($ocupados as $agendamento) {
            $linhas->push([
                '__key' => 'ocupado-'.$agendamento->id,
                'tipo' => 'ocupado',
                'agendamento_id' => $agendamento->id,
                'data' => $agendamento->data_hora_inicio->toDateString(),
                'inicio' => $agendamento->data_hora_inicio,
                'fim' => $agendamento->data_hora_fim,
                'paciente' => $agendamento->paciente?->nome,
                'profissional' => $agendamento->profissional?->nome,
                'especialidade' => $agendamento->especialidade?->nome,
                'situacao' => $agendamento->situacao,
            ]);
        }

        $cursorDia = $inicio->copy()->startOfDay();

        while ($cursorDia->lte($fim)) {
            $periodo = CarbonPeriod::create(
                $cursorDia->copy()->setTimeFromTimeString(self::HORARIO_INICIO_GRADE),
                self::PASSO_MINUTOS.' minutes',
                $cursorDia->copy()->setTimeFromTimeString(self::HORARIO_FIM_GRADE)->subMinutes(self::PASSO_MINUTOS),
            );

            foreach ($periodo as $horario) {
                $horarioFim = $horario->copy()->addMinutes(self::PASSO_MINUTOS);

                $coberto = $ocupados->contains(
                    fn (Agendamento $agendamento) => $agendamento->data_hora_inicio < $horarioFim && $agendamento->data_hora_fim > $horario
                );

                if ($coberto) {
                    continue;
                }

                $linhas->push([
                    '__key' => 'livre-'.$horario->format('YmdHi'),
                    'tipo' => 'livre',
                    'data' => $horario->toDateString(),
                    'inicio' => $horario->copy(),
                    'fim' => $horarioFim,
                    'paciente' => null,
                    'profissional' => null,
                    'especialidade' => null,
                    'situacao' => null,
                ]);
            }

            $cursorDia->addDay();
        }

        return $linhas
            ->sortBy(fn (array $linha) => $linha['inicio']->format('Y-m-d H:i:s'))
            ->values()
            ->all();
    }
}
