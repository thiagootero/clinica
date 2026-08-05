<?php

namespace App\Filament\Pages\Concerns;

use App\Enums\SexoPaciente;
use App\Models\Clinica;
use App\Models\Paciente;
use App\Models\Procedimento;
use App\Services\ServicoHorarioFuncionamento;
use Carbon\Carbon;
use Closure;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;

/**
 * Campos do "corpo" de uma consulta (paciente, procedimentos previstos, descrição) comuns às
 * telas de marcar e editar consulta — reaproveitados por AgendarConsulta, MarcaConsultaRapida,
 * Livewire\Profissionais\AgendaTabela e GerenciaAgendamento (formulário de editar).
 */
trait CamposConsulta
{
    protected function campoPaciente(): Select
    {
        return Select::make('paciente_id')
            ->label('Paciente')
            ->required()
            ->searchable()
            ->options(fn (): array => Paciente::query()->daClinica()->where('ativo', true)->orderBy('nome')->pluck('nome', 'id')->all())
            ->createOptionForm([
                TextInput::make('nome')->required(),
                TextInput::make('cpf')->label('CPF')->mask('999.999.999-99')->unique(table: Paciente::class, ignoreRecord: false),
                Select::make('sexo')->label('Sexo')->options(SexoPaciente::class),
                DatePicker::make('data_nascimento')->label('Data nascimento')->required(),
                TextInput::make('telefone')->label('Telefone')->mask('(99)99999-9999')->required(),
            ])
            ->createOptionUsing(function (array $data): int {
                return Paciente::query()->create([
                    ...$data,
                    'clinica_id' => auth()->user()->clinicaAtivaId(),
                    'criado_por' => auth()->id(),
                ])->id;
            });
    }

    /**
     * @param  Closure(Get): ?int  $especialidadeId
     */
    protected function campoProcedimentoPrevisto(Closure $especialidadeId): Select
    {
        return Select::make('procedimentos_previstos_ids')
            ->label('Procedimentos previstos')
            ->multiple()
            ->searchable()
            ->options(fn (Get $get): array => Procedimento::query()
                ->daClinica()
                ->where('ativo', true)
                ->when($especialidadeId($get), fn ($query, $id) => $query->where('especialidade_id', $id))
                ->orderBy('nome')
                ->pluck('nome', 'id')
                ->all());
    }

    protected function campoDescricao(): Textarea
    {
        return Textarea::make('descricao')
            ->label('Descrição da consulta')
            ->columnSpanFull();
    }

    /**
     * Data + hora + minuto como três selects separados (minuto restrito a 00/15/30/45) em vez de
     * um único DateTimePicker de digitação livre — evita horários fora da grade de 15 em 15
     * minutos da agenda. As opções de hora/minuto já vêm restritas ao horário de funcionamento da
     * clínica no dia selecionado (configurado em Clínica → Horário de funcionamento): o último
     * horário oferecido é sempre o último múltiplo de 15 minutos que ainda cabe um atendimento
     * mínimo de 15 minutos antes do fechamento (ex.: fecha às 18:00 → último horário é 17:45;
     * fecha às 17:30 → último horário é 17:15). Usado só no formulário de "Editar consulta" (a
     * tela de marcar já resolve o horário a partir do slot clicado no calendário, sempre dentro do
     * funcionamento e alinhado à grade).
     *
     * @param  Closure(Get): bool  $visivelQuando
     * @param  Closure(Get): ?Clinica  $clinica
     */
    protected function camposDataHoraEdicao(Closure $visivelQuando, Closure $clinica): array
    {
        $visivel = fn (Get $get): bool => $visivelQuando($get);

        $temContexto = fn (Get $get): bool => (bool) $clinica($get) && filled($get('data_edicao'));

        $janela = function (Get $get) use ($clinica): ?array {
            return app(ServicoHorarioFuncionamento::class)->janelaPara($clinica($get), Carbon::parse($get('data_edicao')));
        };

        // Faixa [primeiroSlot, ultimoSlot] em minutos desde 00:00, já alinhada à grade de 15 em 15
        // minutos, garantindo que o último horário oferecido ainda caiba um atendimento mínimo de
        // 15 minutos antes do fechamento.
        $faixaSlots = function (Get $get) use ($janela): ?array {
            $janelaResolvida = $janela($get);

            if (! $janelaResolvida) {
                return null;
            }

            $abreMinutos = ((int) $janelaResolvida['abre']->format('H') * 60) + (int) $janelaResolvida['abre']->format('i');
            $fechaMinutos = ((int) $janelaResolvida['fecha']->format('H') * 60) + (int) $janelaResolvida['fecha']->format('i');

            $primeiroSlot = (int) (ceil($abreMinutos / 15) * 15);
            $ultimoSlot = (int) (floor(($fechaMinutos - 15) / 15) * 15);

            if ($ultimoSlot < $primeiroSlot) {
                return null;
            }

            return [$primeiroSlot, $ultimoSlot];
        };

        return [
            Grid::make(3)
                ->schema([
                    DatePicker::make('data_edicao')
                        ->label('Data')
                        ->live()
                        ->required($visivel)
                        ->afterStateUpdated(fn (Set $set) => $set('hora_edicao', null))
                        ->helperText(function (Get $get) use ($temContexto, $faixaSlots): ?string {
                            if (! $temContexto($get)) {
                                return null;
                            }

                            return $faixaSlots($get) ? null : 'A clínica não funciona (ou não tem 15 minutos de expediente) neste dia.';
                        }),
                    Select::make('hora_edicao')
                        ->label('Hora')
                        ->options(function (Get $get) use ($temContexto, $faixaSlots): array {
                            if (! $temContexto($get)) {
                                return collect(range(0, 23))->mapWithKeys(fn (int $hora): array => [$hora => sprintf('%02d', $hora)])->all();
                            }

                            $faixa = $faixaSlots($get);

                            if (! $faixa) {
                                return [];
                            }

                            [$primeiroSlot, $ultimoSlot] = $faixa;

                            return collect(range((int) floor($primeiroSlot / 60), (int) floor($ultimoSlot / 60)))
                                ->mapWithKeys(fn (int $hora): array => [$hora => sprintf('%02d', $hora)])
                                ->all();
                        })
                        ->live()
                        ->afterStateUpdated(fn (Set $set) => $set('minuto_edicao', null))
                        ->required($visivel),
                    Select::make('minuto_edicao')
                        ->label('Minuto')
                        ->options(function (Get $get) use ($temContexto, $faixaSlots): array {
                            $minutosPadrao = [0 => '00', 15 => '15', 30 => '30', 45 => '45'];

                            if (! $temContexto($get) || blank($get('hora_edicao'))) {
                                return $minutosPadrao;
                            }

                            $faixa = $faixaSlots($get);

                            if (! $faixa) {
                                return [];
                            }

                            [$primeiroSlot, $ultimoSlot] = $faixa;
                            $hora = (int) $get('hora_edicao');

                            return collect([0, 15, 30, 45])
                                ->filter(fn (int $minuto): bool => ($hora * 60 + $minuto) >= $primeiroSlot && ($hora * 60 + $minuto) <= $ultimoSlot)
                                ->mapWithKeys(fn (int $minuto): array => [$minuto => sprintf('%02d', $minuto)])
                                ->all();
                        })
                        ->live()
                        ->required($visivel),
                ])
                ->visible($visivel),
        ];
    }

    protected function dataHoraEdicao(Get $get): ?Carbon
    {
        return $this->combinarDataHoraEdicao($get('data_edicao'), $get('hora_edicao'), $get('minuto_edicao'));
    }

    protected function combinarDataHoraEdicao(mixed $data, mixed $hora, mixed $minuto): ?Carbon
    {
        if (blank($data) || $hora === null || $hora === '' || $minuto === null || $minuto === '') {
            return null;
        }

        return Carbon::parse($data)->setTime((int) $hora, (int) $minuto);
    }
}
