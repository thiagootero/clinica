<?php

namespace App\Http\Controllers;

use App\Enums\SituacaoAgendamento;
use App\Models\Agendamento;
use App\Models\Profissional;
use App\Models\Sala;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;

class RelatorioDiaController extends Controller
{
    public function show(string $modo, int $entidadeId, string $data): View
    {
        abort_unless(in_array($modo, ['sala', 'profissional'], true), 404);

        $dataCarbon = Carbon::parse($data);

        /** @var Model $entidade */
        $entidade = $modo === 'sala'
            ? Sala::query()->daClinica()->findOrFail($entidadeId)
            : Profissional::query()->daClinica()->findOrFail($entidadeId);

        $agendamentos = Agendamento::query()
            ->where($modo === 'sala' ? 'sala_id' : 'profissional_id', $entidadeId)
            ->whereDate('data_hora_inicio', $dataCarbon->toDateString())
            ->whereNotIn('situacao', [SituacaoAgendamento::Cancelado, SituacaoAgendamento::Remarcado])
            ->with(['paciente', 'profissional', 'procedimentosPrevistos'])
            ->orderBy('data_hora_inicio')
            ->get();

        return view('relatorio-dia', [
            'modo' => $modo,
            'entidade' => $entidade,
            'data' => $dataCarbon,
            'agendamentos' => $agendamentos,
        ]);
    }
}
