<?php

namespace App\Filament\Support;

use App\Enums\SituacaoAgendamento;
use App\Models\Agendamento;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;

/**
 * Blocos do modal de resumo (somente leitura) de uma consulta, reaproveitados em
 * todos os lugares onde um agendamento aparece em lista (dashboard, agendar consulta,
 * pacientes não confirmados, agendas por sala/profissional, histórico do paciente).
 *
 * O consumidor é responsável por eager-loadar paciente, profissional, especialidade,
 * sala, procedimentos, procedimentosPrevistos e registroAtendimento antes de chamar.
 */
class ResumoAgendamentoSchema
{
    /**
     * @return array<int, \Filament\Schemas\Components\Component>
     */
    public static function schema(Agendamento $agendamento): array
    {
        return [
            Section::make('Consulta')
                ->schema([
                    Grid::make(3)
                        ->schema([
                            TextEntry::make('paciente')->label('Paciente')->state($agendamento->paciente?->nome),
                            TextEntry::make('profissional')->label('Profissional')->state($agendamento->profissional?->nome),
                            TextEntry::make('especialidade')->label('Especialidade')->state($agendamento->especialidade?->nome),
                            TextEntry::make('sala')->label('Sala')->state($agendamento->sala?->nome),
                            TextEntry::make('data')->label('Data')->state($agendamento->data_hora_inicio->translatedFormat('d/m/Y, l')),
                            TextEntry::make('horario')->label('Horário')->state(
                                $agendamento->data_hora_inicio->format('H:i').' às '.$agendamento->data_hora_fim->format('H:i')
                            ),
                            TextEntry::make('situacao')
                                ->label('Situação')
                                ->state($agendamento->situacao->getLabel())
                                ->badge()
                                ->color(fn (): string => static::corSituacao($agendamento->situacao)),
                        ]),
                ]),

            Section::make('Descrição da consulta')
                ->schema([
                    TextEntry::make('descricao')->hiddenLabel()->state($agendamento->descricao),
                ])
                ->visible(filled($agendamento->descricao)),

            Section::make('Resumo do atendimento')
                ->schema([
                    TextEntry::make('resumo_atendimento')->hiddenLabel()->state($agendamento->registroAtendimento?->resumo_atendimento),
                ])
                ->visible(filled($agendamento->registroAtendimento?->resumo_atendimento)),

            Section::make($agendamento->situacao === SituacaoAgendamento::Realizado ? 'Procedimentos realizados' : 'Procedimentos previstos')
                ->schema([
                    RepeatableEntry::make('procedimentos')
                        ->hiddenLabel()
                        ->state($agendamento->situacao === SituacaoAgendamento::Realizado ? $agendamento->procedimentos : $agendamento->procedimentosPrevistos)
                        ->schema(array_filter([
                            TextEntry::make('nome')->hiddenLabel(),
                            $agendamento->situacao === SituacaoAgendamento::Realizado
                                ? TextEntry::make('pivot.quantidade')->label('Quantidade')
                                : null,
                        ]))
                        ->columns(2)
                        ->placeholder('Nenhum procedimento.'),
                ]),
        ];
    }

    protected static function corSituacao(SituacaoAgendamento $situacao): string
    {
        return match (true) {
            in_array($situacao, [SituacaoAgendamento::Agendado, SituacaoAgendamento::Confirmado], true) => 'warning',
            $situacao === SituacaoAgendamento::Realizado => 'info',
            in_array($situacao, [SituacaoAgendamento::Cancelado, SituacaoAgendamento::NaoCompareceu], true) => 'danger',
            default => 'gray',
        };
    }
}
