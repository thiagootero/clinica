<?php

namespace App\Filament\Pages\Concerns;

use App\Models\Profissional;
use App\Models\Sala;
use App\Services\ServicoCriacaoAgendamento;
use App\Support\DuracaoAtendimento;
use Carbon\Carbon;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\ValidationException;

trait MarcaConsultaRapida
{
    use CamposConsulta;

    public function marcarConsultaAction(): Action
    {
        return Action::make('marcarConsulta')
            ->label('Marcar')
            ->modalHeading('Marcar consulta')
            ->modalSubmitActionLabel('Agendar')
            ->modalWidth('2xl')
            ->schema(fn (array $arguments): array => $this->modo === 'sala'
                ? $this->schemaMarcarPorSala($arguments)
                : $this->schemaMarcarPorProfissional($arguments))
            ->action(function (array $data, Action $action): void {
                $this->modo === 'sala'
                    ? $this->executarMarcarPorSala($data, $action)
                    : $this->executarMarcarPorProfissional($data, $action);
            });
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<int, \Filament\Schemas\Components\Component>
     */
    protected function schemaMarcarPorProfissional(array $arguments): array
    {
        $profissional = $this->entidadeSelecionada;

        return [
            Hidden::make('inicio')->default($arguments['inicio'] ?? null),

            Select::make('especialidade_id')
                ->label('Especialidade')
                ->options(fn (): array => $profissional?->especialidades->pluck('nome', 'id')->all() ?? [])
                ->visible(fn (): bool => ($profissional?->especialidades->count() ?? 0) > 1)
                ->default(fn (): ?int => $this->especialidadeUnicaDoProfissional($profissional?->id))
                ->live()
                ->required()
                ->afterStateUpdated(fn (Set $set) => $set('procedimentos_previstos_ids', [])),

            Radio::make('tipo_atendimento')
                ->label('Tipo de atendimento')
                ->options(['consulta' => 'Consulta', 'retorno' => 'Retorno'])
                ->inline()
                ->default('consulta')
                ->visible(fn (): bool => (bool) $profissional?->oferece_retorno),

            Select::make('duracao_minutos')
                ->label('Duração')
                ->options(DuracaoAtendimento::options())
                ->required()
                ->live()
                ->default($arguments['duracaoMinutos'] ?? 30),

            ...$this->camposSalaComAvisoTroca(
                profissionalId: fn (Get $get): ?int => $profissional?->id,
                inicio: fn (Get $get): ?Carbon => filled($get('inicio')) ? $this->inicioDoFormulario($get) : null,
                duracaoMinutos: fn (Get $get): int => $this->duracaoDoFormulario($get),
            ),

            $this->campoPaciente(),
            $this->campoProcedimentoPrevisto(fn (Get $get): ?int => filled($get('especialidade_id')) ? (int) $get('especialidade_id') : $this->especialidadeUnicaDoProfissional($profissional?->id)),
            $this->campoDescricao(),
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<int, \Filament\Schemas\Components\Component>
     */
    protected function schemaMarcarPorSala(array $arguments): array
    {
        $sala = $this->entidadeSelecionada;

        return [
            Hidden::make('inicio')->default($arguments['inicio'] ?? null),

            Select::make('profissional_id')
                ->label('Profissional')
                ->required()
                ->live()
                ->options(fn (): array => $this->profissionaisDaSala($sala)->pluck('nome', 'id')->all())
                ->default($arguments['profissionalId'] ?? null)
                ->afterStateUpdated(function (Set $set, $state): void {
                    $set('especialidade_id', $this->especialidadeUnicaDoProfissional($state));
                })
                ->helperText(fn (): ?string => $this->profissionaisDaSala($sala)->isEmpty()
                    ? 'Nenhum profissional habilitado para esta sala. Vincule em Profissionais → Salas de Atendimento.'
                    : null),

            Select::make('especialidade_id')
                ->label('Especialidade')
                ->required()
                ->live()
                ->visible(fn (Get $get): bool => filled($get('profissional_id')) && (Profissional::find($get('profissional_id'))?->especialidades->count() ?? 0) > 1)
                ->options(fn (Get $get): array => Profissional::find($get('profissional_id'))?->especialidades->pluck('nome', 'id')->all() ?? [])
                ->default(fn (): ?int => $this->especialidadeUnicaDoProfissional($arguments['profissionalId'] ?? null))
                ->afterStateUpdated(fn (Set $set) => $set('procedimentos_previstos_ids', [])),

            Radio::make('tipo_atendimento')
                ->label('Tipo de atendimento')
                ->options(['consulta' => 'Consulta', 'retorno' => 'Retorno'])
                ->inline()
                ->default('consulta')
                ->visible(fn (Get $get): bool => (bool) Profissional::find($get('profissional_id'))?->oferece_retorno),

            Select::make('duracao_minutos')
                ->label('Duração')
                ->options(DuracaoAtendimento::options())
                ->required()
                ->live()
                ->default($arguments['duracaoMinutos'] ?? 30),

            Toggle::make('confirmar_disponibilidade_extra')
                ->label(fn (Get $get): HtmlString => $this->avisoDisponibilidadeExtraHtml($get))
                ->live()
                ->dehydrated(false)
                ->visible(fn (Get $get): bool => $this->precisaDeDisponibilidadeExtra($get))
                ->rule(function (Get $get): Closure {
                    return function (string $attribute, $value, Closure $fail) use ($get): void {
                        if ($this->precisaDeDisponibilidadeExtra($get) && ! $value) {
                            $fail('Confirme a criação da disponibilidade extra para prosseguir.');
                        }
                    };
                }),

            $this->campoPaciente(),
            $this->campoProcedimentoPrevisto(fn (Get $get): ?int => filled($get('especialidade_id'))
                ? (int) $get('especialidade_id')
                : $this->especialidadeUnicaDoProfissional($get('profissional_id'))),
            $this->campoDescricao(),
        ];
    }

    protected function especialidadeUnicaDoProfissional(mixed $profissionalId): ?int
    {
        if (blank($profissionalId)) {
            return null;
        }

        $especialidades = Profissional::find($profissionalId)?->especialidades;

        return $especialidades?->count() === 1 ? $especialidades->first()->id : null;
    }

    protected function inicioDoFormulario(Get $get): Carbon
    {
        return Carbon::parse($get('inicio'));
    }

    protected function duracaoDoFormulario(Get $get): int
    {
        return (int) ($get('duracao_minutos') ?: 30);
    }

    /**
     * @return Collection<int, Profissional>
     */
    protected function profissionaisDaSala(Sala $sala): Collection
    {
        return $sala->profissionais()
            ->wherePivot('ativo', true)
            ->where('profissionais.ativo', true)
            ->orderBy('profissionais.nome')
            ->get();
    }

    protected function precisaDeDisponibilidadeExtra(Get $get): bool
    {
        $profissional = Profissional::find($get('profissional_id'));

        if (! $profissional || blank($get('duracao_minutos'))) {
            return false;
        }

        $inicio = $this->inicioDoFormulario($get);
        $fim = $inicio->copy()->addMinutes($this->duracaoDoFormulario($get));

        return ! $this->servicoDisponibilidadeAgenda()->estaDentroDaDisponibilidade($profissional, $inicio, $fim);
    }

    protected function avisoDisponibilidadeExtraHtml(Get $get): HtmlString
    {
        $nome = Profissional::find($get('profissional_id'))?->nome;
        $confirmado = (bool) $get('confirmar_disponibilidade_extra');
        $cor = $confirmado ? 'text-success-600 dark:text-success-400' : 'text-danger-600 dark:text-danger-400';

        return new HtmlString(
            '<span class="'.$cor.'">'.e($nome).' não possui disponibilidade prévia nesse horário. '
            .'Confirmo que o profissional aceitou atender neste horário.</span>'
        );
    }

    protected function executarMarcarPorProfissional(array $data, Action $action): void
    {
        $profissional = $this->entidadeSelecionada;

        if (! $profissional instanceof Profissional) {
            return;
        }

        $inicio = Carbon::parse($data['inicio']);

        try {
            app(ServicoCriacaoAgendamento::class)->executar([
                'paciente_id' => $data['paciente_id'],
                'profissional_id' => $profissional->id,
                'especialidade_id' => $data['especialidade_id'] ?? $this->especialidadeUnicaDoProfissional($profissional->id),
                'procedimentos_previstos_ids' => $data['procedimentos_previstos_ids'] ?? [],
                'sala_id' => $data['sala_id'],
                'data_hora_inicio' => $inicio,
                'duracao_minutos' => $data['duracao_minutos'],
                'tipo_atendimento' => $data['tipo_atendimento'] ?? 'consulta',
                'descricao' => $data['descricao'] ?? null,
            ], auth()->user());
        } catch (ValidationException $exception) {
            Notification::make()
                ->danger()
                ->title('Não foi possível agendar')
                ->body(collect($exception->errors())->flatten()->implode(' '))
                ->send();

            $action->halt();

            return;
        }

        Notification::make()->success()->title('Agendamento criado com sucesso.')->send();
    }

    protected function executarMarcarPorSala(array $data, Action $action): void
    {
        $sala = $this->entidadeSelecionada;
        $profissional = Profissional::find($data['profissional_id'] ?? null);

        if (! $sala instanceof Sala || ! $profissional) {
            return;
        }

        $inicio = Carbon::parse($data['inicio']);
        $duracao = (int) $data['duracao_minutos'];
        $fim = $inicio->copy()->addMinutes($duracao);

        try {
            DB::transaction(function () use ($profissional, $sala, $inicio, $fim, $duracao, $data): void {
                $this->servicoDisponibilidadeAgenda()->garantirDisponibilidade(
                    $profissional,
                    $sala,
                    $inicio,
                    $fim,
                    true,
                );

                app(ServicoCriacaoAgendamento::class)->executar([
                    'paciente_id' => $data['paciente_id'],
                    'profissional_id' => $profissional->id,
                    'especialidade_id' => $data['especialidade_id'] ?? $this->especialidadeUnicaDoProfissional($profissional->id),
                    'procedimentos_previstos_ids' => $data['procedimentos_previstos_ids'] ?? [],
                    'sala_id' => $sala->id,
                    'data_hora_inicio' => $inicio,
                    'duracao_minutos' => $duracao,
                    'tipo_atendimento' => $data['tipo_atendimento'] ?? 'consulta',
                    'descricao' => $data['descricao'] ?? null,
                ], auth()->user());
            });
        } catch (ValidationException $exception) {
            Notification::make()
                ->danger()
                ->title('Não foi possível agendar')
                ->body(collect($exception->errors())->flatten()->implode(' '))
                ->send();

            $action->halt();

            return;
        }

        Notification::make()->success()->title('Agendamento criado com sucesso.')->send();
    }
}
