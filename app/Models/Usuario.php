<?php

namespace App\Models;

use App\Enums\PerfilUsuario;
use Database\Factories\UsuarioFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasName;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Session;

class Usuario extends Authenticatable implements FilamentUser, HasName
{
    use HasFactory;
    use Notifiable;
    use SoftDeletes;

    /**
     * Chave de sessão onde o Administrador guarda qual clínica está visualizando.
     */
    public const SESSAO_CLINICA_ATIVA = 'clinica_ativa_id';

    protected $table = 'usuarios';

    protected $fillable = [
        'clinica_id',
        'nome',
        'email',
        'senha',
        'perfil',
        'ativo',
    ];

    protected $hidden = [
        'senha',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verificado_em' => 'datetime',
            'senha' => 'hashed',
            'ativo' => 'boolean',
            'perfil' => PerfilUsuario::class,
        ];
    }

    protected static function newFactory(): UsuarioFactory
    {
        return UsuarioFactory::new();
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->ativo;
    }

    public function getFilamentName(): string
    {
        return $this->nome;
    }

    public function clinica(): BelongsTo
    {
        return $this->belongsTo(Clinica::class, 'clinica_id');
    }

    public function getAuthPassword(): string
    {
        return $this->senha;
    }

    public function hasRole(PerfilUsuario|string $perfil): bool
    {
        $valor = $perfil instanceof PerfilUsuario ? $perfil : PerfilUsuario::from($perfil);

        return $this->perfil === $valor;
    }

    public function ehAdministrador(): bool
    {
        return $this->hasRole(PerfilUsuario::Administrador);
    }

    /**
     * Gerente está sempre preso à própria clínica. Administrador não pertence a
     * nenhuma clínica fixa: ele escolhe qual clínica está visualizando/operando
     * no momento, e essa escolha fica guardada na sessão.
     */
    public function clinicaAtivaId(): ?int
    {
        if (! $this->ehAdministrador()) {
            return $this->clinica_id;
        }

        $clinicaId = Session::get(self::SESSAO_CLINICA_ATIVA);

        return $clinicaId ? (int) $clinicaId : null;
    }

    public function clinicaAtiva(): ?Clinica
    {
        $clinicaId = $this->clinicaAtivaId();

        return $clinicaId ? Clinica::query()->find($clinicaId) : null;
    }
}
