<?php

namespace App\Providers;

use App\Models\Agendamento;
use App\Models\Clinica;
use App\Models\DisponibilidadeProfissional;
use App\Models\Especialidade;
use App\Models\ExcecaoFuncionamento;
use App\Models\HorarioFuncionamento;
use App\Models\Paciente;
use App\Models\Procedimento;
use App\Models\Profissional;
use App\Models\RegistroAtendimento;
use App\Models\Sala;
use App\Observers\AgendamentoObserver;
use App\Observers\AuditoriaObserver;
use App\Observers\ClinicaObserver;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Agendamento::observe(AgendamentoObserver::class);
        Agendamento::observe(AuditoriaObserver::class);
        Paciente::observe(AuditoriaObserver::class);
        Profissional::observe(AuditoriaObserver::class);
        Especialidade::observe(AuditoriaObserver::class);
        Sala::observe(AuditoriaObserver::class);
        DisponibilidadeProfissional::observe(AuditoriaObserver::class);
        Procedimento::observe(AuditoriaObserver::class);
        RegistroAtendimento::observe(AuditoriaObserver::class);
        HorarioFuncionamento::observe(AuditoriaObserver::class);
        ExcecaoFuncionamento::observe(AuditoriaObserver::class);
        Clinica::observe(ClinicaObserver::class);

        // Todos os formulários do sistema são de uma coluna só. Usa um modal
        // largo o bastante para ler bem os campos sem sobrar espaço vazio de
        // uma "segunda coluna", exceto nas confirmações (excluir), que já
        // usam uma largura menor por padrão.
        Action::configureUsing(function (Action $action): void {
            if ($action instanceof DeleteAction) {
                return;
            }

            $action->modalWidth('2xl');
        });
    }
}
