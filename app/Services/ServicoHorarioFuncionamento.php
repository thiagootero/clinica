<?php

namespace App\Services;

use App\Models\Clinica;
use App\Models\ExcecaoFuncionamento;
use App\Models\HorarioFuncionamento;
use Carbon\Carbon;

class ServicoHorarioFuncionamento
{
    /**
     * Janela de funcionamento da clínica numa data: prioriza uma exceção cadastrada para o dia
     * exato (feriado ou horário reduzido); na ausência dela, usa o horário semanal padrão.
     * Retorna null quando a clínica está fechada nesse dia.
     *
     * @return array{abre: Carbon, fecha: Carbon}|null
     */
    public function janelaPara(Clinica $clinica, Carbon $data): ?array
    {
        $excecao = ExcecaoFuncionamento::query()
            ->where('clinica_id', $clinica->id)
            ->whereDate('data', $data->toDateString())
            ->first();

        if ($excecao) {
            if ($excecao->fechado) {
                return null;
            }

            return [
                'abre' => Carbon::parse($data->toDateString().' '.$excecao->abre_em),
                'fecha' => Carbon::parse($data->toDateString().' '.$excecao->fecha_em),
            ];
        }

        $horario = HorarioFuncionamento::query()
            ->where('clinica_id', $clinica->id)
            ->where('dia_semana', $data->isoWeekday())
            ->first();

        if (! $horario || $horario->fechado) {
            return null;
        }

        return [
            'abre' => Carbon::parse($data->toDateString().' '.$horario->abre_em),
            'fecha' => Carbon::parse($data->toDateString().' '.$horario->fecha_em),
        ];
    }

    public function estaDentroDoHorario(Clinica $clinica, Carbon $inicio, Carbon $fim): bool
    {
        $janela = $this->janelaPara($clinica, $inicio->copy()->startOfDay());

        if (! $janela) {
            return false;
        }

        return $inicio->toDateString() === $fim->toDateString()
            && $inicio->gte($janela['abre'])
            && $fim->lte($janela['fecha']);
    }

    /**
     * Mensagem pronta para hint/validação, ou null se o intervalo está dentro do horário de
     * funcionamento.
     */
    public function motivoForaDoHorario(Clinica $clinica, Carbon $inicio, Carbon $fim): ?string
    {
        if ($this->estaDentroDoHorario($clinica, $inicio, $fim)) {
            return null;
        }

        $janela = $this->janelaPara($clinica, $inicio->copy()->startOfDay());

        if (! $janela) {
            return 'A clínica não funciona neste dia.';
        }

        return "Fora do horário de funcionamento da clínica ({$janela['abre']->format('H:i')} às {$janela['fecha']->format('H:i')}).";
    }
}
