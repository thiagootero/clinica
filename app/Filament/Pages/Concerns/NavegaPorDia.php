<?php

namespace App\Filament\Pages\Concerns;

use Carbon\Carbon;

trait NavegaPorDia
{
    public function diaAnterior(): void
    {
        $this->data = Carbon::parse($this->data)->subDay()->toDateString();
    }

    public function diaProximo(): void
    {
        $this->data = Carbon::parse($this->data)->addDay()->toDateString();
    }
}
