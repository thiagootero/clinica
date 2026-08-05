<?php

namespace App\Services;

use App\Enums\SituacaoDisponibilidade;
use App\Models\DisponibilidadeProfissional;
use App\Models\Profissional;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class ServicoConflitoSalaPredefinida
{
    /**
     * Disponibilidades ativas de OUTROS profissionais na mesma sala e data, com horário sobreposto.
     * Predefinir a sala aqui nunca deve ser permitido.
     */
    public function conflitosSobrepostos(
        int $salaId,
        string $data,
        string $horarioInicio,
        string $horarioFim,
        int $profissionalId,
        ?int $disponibilidadeIgnoradaId = null,
    ): Collection {
        return $this->disponibilidadesNaSalaEData($salaId, $data, $profissionalId, $disponibilidadeIgnoradaId)
            ->filter(fn (DisponibilidadeProfissional $d) => $d->horario_inicio < $horarioFim && $d->horario_fim > $horarioInicio)
            ->values();
    }

    protected function disponibilidadesNaSalaEData(
        int $salaId,
        string $data,
        int $profissionalId,
        ?int $disponibilidadeIgnoradaId,
    ): Collection {
        return DisponibilidadeProfissional::query()
            ->where('sala_id', $salaId)
            ->whereDate('data_disponibilidade', $data)
            ->where('situacao', SituacaoDisponibilidade::Ativa)
            ->where('profissional_id', '!=', $profissionalId)
            ->when($disponibilidadeIgnoradaId, fn ($query) => $query->whereKeyNot($disponibilidadeIgnoradaId))
            ->with('profissional')
            ->get();
    }

    /**
     * Sala predefinida na disponibilidade ativa do profissional que cobre integralmente o intervalo informado.
     */
    public function salaPredefinida(int $profissionalId, Carbon $inicio, Carbon $fim): ?int
    {
        return DisponibilidadeProfissional::query()
            ->where('profissional_id', $profissionalId)
            ->whereDate('data_disponibilidade', $inicio->toDateString())
            ->where('situacao', SituacaoDisponibilidade::Ativa)
            ->whereNotNull('sala_id')
            ->where('horario_inicio', '<=', $inicio->format('H:i:s'))
            ->where('horario_fim', '>=', $fim->format('H:i:s'))
            ->value('sala_id');
    }

    /**
     * Profissional dono da sala nesse intervalo (disponibilidade ativa de OUTRO profissional com
     * essa sala predefinida e horário sobreposto), se houver.
     */
    public function profissionalDonoDaSala(int $salaId, Carbon $inicio, Carbon $fim, int $profissionalIgnorado): ?Profissional
    {
        return DisponibilidadeProfissional::query()
            ->where('sala_id', $salaId)
            ->whereDate('data_disponibilidade', $inicio->toDateString())
            ->where('situacao', SituacaoDisponibilidade::Ativa)
            ->where('profissional_id', '!=', $profissionalIgnorado)
            ->where('horario_inicio', '<', $fim->format('H:i:s'))
            ->where('horario_fim', '>', $inicio->format('H:i:s'))
            ->with('profissional')
            ->first()
            ?->profissional;
    }

    /**
     * Mensagem de aviso se a sala escolhida exigir confirmação extra do usuário — porque já está
     * predefinida para OUTRO profissional nesse horário, ou porque é diferente da sala predefinida
     * para o próprio profissional sendo agendado. Retorna null se a escolha é segura.
     */
    public function avisoTrocaSala(int $salaEscolhidaId, int $profissionalId, Carbon $inicio, Carbon $fim): ?string
    {
        $dono = $this->profissionalDonoDaSala($salaEscolhidaId, $inicio, $fim, $profissionalId);

        if ($dono) {
            return "A sala já está predefinida para {$dono->nome} nesse horário. Trocar exige confirmação abaixo.";
        }

        $predefinida = $this->salaPredefinida($profissionalId, $inicio, $fim);

        if ($predefinida && $predefinida !== $salaEscolhidaId) {
            return 'A sala predefinida para este profissional/horário é outra. Trocar exige confirmação abaixo.';
        }

        return null;
    }
}
