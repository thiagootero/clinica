<?php

namespace App\Filament\Pages\Concerns;

use App\Enums\SituacaoAgendamento;
use App\Enums\SituacaoDisponibilidade;
use App\Models\Agendamento;
use App\Models\DisponibilidadeProfissional;
use App\Models\Profissional;
use App\Models\Sala;
use App\Services\ServicoAgendaSala;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;

trait ExibeAgendaDoDia
{
    private const HORARIO_PADRAO_SALA_INICIO = '08:00';

    private const HORARIO_PADRAO_SALA_FIM = '18:00';

    private const DURACAO_PADRAO_SALA_MINUTOS = 15;

    public string $modo = 'sala';

    public function getSalasProperty(): Collection
    {
        return Sala::query()->where('ativo', true)->orderBy('nome')->get();
    }

    public function getProfissionaisProperty(): Collection
    {
        return Profissional::query()
            ->where('ativo', true)
            ->whereHas('disponibilidades', function ($query): void {
                $query->whereDate('data_disponibilidade', $this->data)
                    ->where('situacao', SituacaoDisponibilidade::Ativa);
            })
            ->orderBy('nome')
            ->get();
    }

    public function agendaDaSala(Sala $sala): Collection
    {
        return Agendamento::query()
            ->where('sala_id', $sala->id)
            ->whereDate('data_hora_inicio', $this->data)
            ->whereNotIn('situacao', [SituacaoAgendamento::Cancelado, SituacaoAgendamento::Remarcado])
            ->with(['paciente', 'profissional'])
            ->orderBy('data_hora_inicio')
            ->get();
    }

    public function agendaDoProfissional(Profissional $profissional): Collection
    {
        return Agendamento::query()
            ->where('profissional_id', $profissional->id)
            ->whereDate('data_hora_inicio', $this->data)
            ->whereNotIn('situacao', [SituacaoAgendamento::Cancelado, SituacaoAgendamento::Remarcado])
            ->with(['paciente', 'sala'])
            ->orderBy('data_hora_inicio')
            ->get();
    }

    /**
     * Agenda da sala combinada com os intervalos livres do horário padrão de
     * funcionamento (08:00–18:00), segmentados em blocos de 30 minutos. Um
     * bloco livre que já tem um profissional com essa sala predefinida na
     * disponibilidade dele carrega esse profissional em "reservadoPara",
     * mesmo sem consulta marcada ainda.
     */
    public function timelineDaSala(Sala $sala): SupportCollection
    {
        $ocupados = $this->agendaDaSala($sala);

        $janelaInicio = Carbon::parse($this->data.' '.self::HORARIO_PADRAO_SALA_INICIO);
        $janelaFim = Carbon::parse($this->data.' '.self::HORARIO_PADRAO_SALA_FIM);

        $livres = $this->livresNaJanela($ocupados, $janelaInicio, $janelaFim, self::DURACAO_PADRAO_SALA_MINUTOS);

        $predefinidas = $this->servicoAgendaSala()->disponibilidadesPredefinidasPorSala($sala, Carbon::parse($this->data));

        return $this->montarTimeline($ocupados, $livres, $predefinidas);
    }

    protected function servicoAgendaSala(): ServicoAgendaSala
    {
        return app(ServicoAgendaSala::class);
    }

    /**
     * Combina os agendamentos do profissional com os intervalos livres da sua
     * disponibilidade do dia, descontando os horários já ocupados e o
     * intervalo (pausa) cadastrado em cada disponibilidade.
     */
    public function timelineDoProfissional(Profissional $profissional): SupportCollection
    {
        $ocupados = $this->agendaDoProfissional($profissional);

        $disponibilidades = DisponibilidadeProfissional::query()
            ->where('profissional_id', $profissional->id)
            ->whereDate('data_disponibilidade', $this->data)
            ->where('situacao', SituacaoDisponibilidade::Ativa)
            ->orderBy('horario_inicio')
            ->get();

        $livres = collect();

        foreach ($disponibilidades as $disponibilidade) {
            $janelaInicio = Carbon::parse($this->data.' '.$disponibilidade->horario_inicio);
            $janelaFim = Carbon::parse($this->data.' '.$disponibilidade->horario_fim);

            $duracao = $disponibilidade->duracao_atendimento_minutos
                ?: $disponibilidade->especialidade?->duracao_padrao_minutos
                ?: $profissional->duracao_padrao_atendimento
                ?: self::DURACAO_PADRAO_SALA_MINUTOS;

            $intervalo = ($disponibilidade->intervalo_inicio && $disponibilidade->intervalo_fim)
                ? [
                    Carbon::parse($this->data.' '.$disponibilidade->intervalo_inicio),
                    Carbon::parse($this->data.' '.$disponibilidade->intervalo_fim),
                ]
                : null;

            $livres = $livres->merge($this->livresNaJanela($ocupados, $janelaInicio, $janelaFim, $duracao, $intervalo));
        }

        return $this->montarTimeline($ocupados, $livres);
    }

    /**
     * Calcula os blocos livres (já segmentados pela duração padrão) dentro de
     * uma janela de horário, descontando os agendamentos que a sobrepõem e,
     * opcionalmente, um intervalo de pausa.
     *
     * @param  array{0: Carbon, 1: Carbon}|null  $intervalo
     */
    private function livresNaJanela(Collection $ocupados, Carbon $janelaInicio, Carbon $janelaFim, int $duracaoMinutos, ?array $intervalo = null): SupportCollection
    {
        $bloqueios = $ocupados
            ->filter(fn (Agendamento $a): bool => $a->data_hora_inicio < $janelaFim && $a->data_hora_fim > $janelaInicio)
            ->map(fn (Agendamento $a): array => [
                $a->data_hora_inicio->max($janelaInicio),
                $a->data_hora_fim->min($janelaFim),
            ]);

        if ($intervalo) {
            $bloqueios->push($intervalo);
        }

        $livres = collect();
        $cursor = $janelaInicio->copy();

        foreach ($bloqueios->sortBy(fn (array $b) => $b[0])->values() as [$bloqueioInicio, $bloqueioFim]) {
            if ($bloqueioInicio->gt($cursor)) {
                $this->segmentarLivre($livres, $cursor, $bloqueioInicio, $duracaoMinutos);
            }

            if ($bloqueioFim->gt($cursor)) {
                $cursor = $bloqueioFim->copy();
            }
        }

        if ($janelaFim->gt($cursor)) {
            $this->segmentarLivre($livres, $cursor, $janelaFim, $duracaoMinutos);
        }

        return $livres;
    }

    /**
     * Divide um intervalo livre em blocos do tamanho da duração padrão de
     * atendimento, descartando a sobra final menor que um bloco completo.
     */
    private function segmentarLivre(SupportCollection $livres, Carbon $inicio, Carbon $fim, int $duracaoMinutos): void
    {
        $cursor = $inicio->copy();

        while ($cursor->copy()->addMinutes($duracaoMinutos)->lte($fim)) {
            $livres->push(['inicio' => $cursor->copy(), 'fim' => $cursor->copy()->addMinutes($duracaoMinutos)]);
            $cursor->addMinutes($duracaoMinutos);
        }
    }

    /**
     * Normaliza agendamentos (ocupados) e blocos livres para o mesmo formato
     * de item de timeline e devolve tudo ordenado por horário de início. Se
     * $predefinidas for informado, um bloco livre coberto por uma delas
     * carrega o profissional dono dessa predefinição em "reservadoPara".
     */
    private function montarTimeline(Collection $ocupados, SupportCollection $livres, ?Collection $predefinidas = null): SupportCollection
    {
        $itensOcupados = $ocupados->toBase()->map(fn (Agendamento $a): array => [
            'tipo' => 'ocupado',
            'inicio' => $a->data_hora_inicio,
            'fim' => $a->data_hora_fim,
            'agendamento' => $a,
            'reservadoPara' => null,
        ]);

        $itensLivres = $livres->map(function (array $l) use ($predefinidas): array {
            $predefinida = $predefinidas?->first(
                fn (DisponibilidadeProfissional $d): bool => $d->horario_inicio < $l['fim']->format('H:i:s')
                    && $d->horario_fim > $l['inicio']->format('H:i:s')
            );

            return [
                'tipo' => 'livre',
                'inicio' => $l['inicio'],
                'fim' => $l['fim'],
                'agendamento' => null,
                'reservadoPara' => $predefinida?->profissional,
            ];
        });

        return $itensOcupados
            ->merge($itensLivres)
            ->sortBy('inicio')
            ->values();
    }
}
