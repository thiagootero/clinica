<?php

namespace App\Filament\Pages;

use App\Filament\Pages\Concerns\NavegaPorDia;
use App\Models\Sala;
use App\Services\ServicoAgendaSala;
use Carbon\Carbon;
use Filament\Pages\Page;
use Illuminate\Database\Eloquent\Collection;
use UnitEnum;

class ComparativoSalas extends Page
{
    use NavegaPorDia;

    protected static ?string $navigationLabel = 'Comparativo entre salas';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-squares-2x2';

    protected static string|UnitEnum|null $navigationGroup = 'Agenda';

    protected static ?int $navigationSort = 3;

    protected string $view = 'filament.pages.comparativo-salas';

    public string $data;

    public function mount(): void
    {
        $this->data = now()->toDateString();
    }

    public function getSalasProperty(): Collection
    {
        return Sala::query()->daClinica()->where('ativo', true)->orderBy('nome')->get();
    }

    public function getQuadroProperty(ServicoAgendaSala $servico)
    {
        return $servico->quadroSalas($this->salas, Carbon::parse($this->data));
    }
}
