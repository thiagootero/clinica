<?php

namespace App\Filament\Pages;

use App\Enums\SituacaoAgendamento;
use App\Filament\Pages\Concerns\ExibeAgendaDoDia;
use App\Filament\Pages\Concerns\GerenciaAgendamento;
use App\Filament\Pages\Concerns\MarcaConsultaRapida;
use App\Filament\Pages\Concerns\NavegaPorDia;
use App\Models\Agendamento;
use App\Models\Profissional;
use App\Models\Sala;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Panel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class DashboardDia extends Page
{
    use ExibeAgendaDoDia;
    use GerenciaAgendamento;
    use MarcaConsultaRapida;
    use NavegaPorDia;

    protected static ?string $navigationLabel = 'Página Inicial';

    protected static ?string $title = 'Dashboard';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-home';

    protected static ?int $navigationSort = -2;

    protected string $view = 'filament.pages.dashboard-dia';

    public static function getRoutePath(Panel $panel): string
    {
        return '/';
    }

    public string $data;

    public ?int $entidadeId = null;

    public function mount(): void
    {
        $this->data = now()->toDateString();
        $this->garantirEntidadeValida();
    }

    public function updatedModo(): void
    {
        $this->garantirEntidadeValida();
    }

    public function updatedData(): void
    {
        $this->garantirEntidadeValida();
    }

    public function selecionarEntidade(int $id): void
    {
        $this->entidadeId = $id;
    }

    protected function garantirEntidadeValida(): void
    {
        $entidades = $this->modo === 'sala' ? $this->salas : $this->profissionais;

        if (! $entidades->contains('id', $this->entidadeId)) {
            $this->entidadeId = $entidades->first()?->id;
        }
    }

    public function getEntidadeSelecionadaProperty(): Sala|Profissional|null
    {
        if (! $this->entidadeId) {
            return null;
        }

        $entidades = $this->modo === 'sala' ? $this->salas : $this->profissionais;

        return $entidades->firstWhere('id', $this->entidadeId);
    }

    public function getAgendaSelecionadaProperty(): Collection
    {
        $entidade = $this->entidadeSelecionada;

        if (! $entidade) {
            return collect();
        }

        return $this->modo === 'sala' ? $this->timelineDaSala($entidade) : $this->timelineDoProfissional($entidade);
    }

    public function pacientesPendentesAction(): Action
    {
        return Action::make('pacientesPendentes')
            ->label('Pendentes de confirmação')
            ->icon('heroicon-o-bell-alert')
            ->color('warning')
            ->badge(fn (): int => $this->pacientesPendentesQuery()->count())
            ->url(fn (): string => PacientesNaoConfirmados::getUrl());
    }

    protected function pacientesPendentesQuery(): Builder
    {
        return Agendamento::query()
            ->where('situacao', SituacaoAgendamento::Agendado)
            ->whereNull('confirmado_em')
            ->whereDate('data_hora_inicio', $this->data)
            ->with(['paciente', 'profissional', 'especialidade', 'sala'])
            ->orderBy('data_hora_inicio');
    }
}
