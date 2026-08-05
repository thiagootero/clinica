<?php

namespace App\Services;

use Carbon\Carbon;

class ServicoIntervaloAgenda
{
    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    public function intervalo(Carbon $data, string $visao): array
    {
        return match ($visao) {
            'semana' => [$data->copy()->startOfWeek(), $data->copy()->endOfWeek()],
            'mes' => [$data->copy()->startOfMonth(), $data->copy()->endOfMonth()],
            default => [$data->copy()->startOfDay(), $data->copy()->endOfDay()],
        };
    }
}
