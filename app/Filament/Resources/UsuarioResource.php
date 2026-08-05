<?php

namespace App\Filament\Resources;

use App\Enums\PerfilUsuario;
use App\Filament\Pages\UsuarioDetalhe;
use App\Filament\Resources\UsuarioResource\Pages\GerenciarUsuarios;
use App\Models\Usuario;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class UsuarioResource extends Resource
{
    protected static ?string $model = Usuario::class;

    protected static ?string $modelLabel = 'usuário';

    protected static ?string $pluralModelLabel = 'usuários';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationLabel = 'Listar';

    protected static string|UnitEnum|null $navigationGroup = 'Usuários';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Usuário')
                ->schema([
                    Hidden::make('clinica_id')->default(fn () => auth()->user()?->clinicaAtivaId()),
                    TextInput::make('nome')->required(),
                    TextInput::make('email')->email()->required()->unique(ignoreRecord: true),
                    Select::make('perfil')
                        ->options(fn () => auth()->user()?->ehAdministrador()
                            ? PerfilUsuario::class
                            : [PerfilUsuario::Gerente->value => PerfilUsuario::Gerente->getLabel()])
                        ->required(),
                    TextInput::make('senha')
                        ->password()
                        ->dehydrated(fn (?string $state): bool => filled($state))
                        ->required(fn (string $operation): bool => $operation === 'create'),
                    Toggle::make('ativo')->default(true),
                ]),
        ])->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nome')->searchable(),
                TextColumn::make('email'),
                TextColumn::make('clinica.nome')->label('Clínica')->placeholder('Nenhuma (administrador)'),
                TextColumn::make('perfil')->badge(),
                IconColumn::make('ativo')->boolean(),
            ])
            ->defaultSort('nome')
            ->recordUrl(fn (Usuario $record): string => UsuarioDetalhe::getUrl(['registro' => $record->id]));
    }

    public static function getPages(): array
    {
        return ['index' => GerenciarUsuarios::route('/')];
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $clinicaId = auth()->user()?->clinicaAtivaId();

        if ($clinicaId) {
            return $query->where('clinica_id', $clinicaId);
        }

        // Sem clínica ativa selecionada (Administrador), não mistura usuários
        // de clínicas diferentes.
        return $query->whereRaw('1 = 0');
    }
}
