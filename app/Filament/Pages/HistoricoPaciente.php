<?php

namespace App\Filament\Pages;

use App\Filament\Resources\PacienteResource;
use App\Filament\Support\ExclusaoComHistorico;
use App\Models\Especialidade;
use App\Models\Paciente;
use App\Models\Procedimento;
use App\Models\Profissional;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Collection;

class HistoricoPaciente extends Page
{
    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $slug = 'pacientes/{registro}/historico';

    protected string $view = 'filament.pages.historico-paciente';

    public Paciente $registro;

    public string $aba = 'proximos';

    public ?string $dataInicial = null;

    public ?string $dataFinal = null;

    public ?int $especialidadeId = null;

    public ?int $profissionalId = null;

    public ?int $procedimentoId = null;

    public function mount(Paciente $registro): void
    {
        $this->registro = $registro;
    }

    public static function canAccess(array $parameters = []): bool
    {
        return (bool) auth()->user()?->can('viewAny', Paciente::class);
    }

    public function getTitle(): string
    {
        return 'Histórico de '.$this->registro->nome;
    }

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make()
                ->record($this->registro)
                ->schema(fn (Schema $schema): Schema => PacienteResource::form($schema)),
            ExclusaoComHistorico::configurar(
                DeleteAction::make()->record($this->registro),
                fn () => redirect(PacienteResource::getUrl()),
            ),
        ];
    }

    public function dadosPessoaisInfolist(Schema $schema): Schema
    {
        return $schema
            ->record($this->registro)
            ->columns(3)
            ->components([
                TextEntry::make('nome'),
                TextEntry::make('cpf')->label('CPF')->placeholder('-'),
                TextEntry::make('sexo'),
                TextEntry::make('data_nascimento')->label('Nascimento')->date('d/m/Y'),
                TextEntry::make('telefone'),
                TextEntry::make('telefone_secundario')->label('Telefone secundário')->placeholder('-'),
            ]);
    }

    public function getEspecialidadesProperty(): Collection
    {
        return Especialidade::query()->orderBy('nome')->get();
    }

    public function getProfissionaisProperty(): Collection
    {
        return Profissional::query()->orderBy('nome')->get();
    }

    public function getProcedimentosProperty(): Collection
    {
        return Procedimento::query()->orderBy('nome')->get();
    }
}
