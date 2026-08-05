<?php

namespace Database\Factories;

use App\Enums\PerfilUsuario;
use App\Models\Clinica;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<Usuario>
 */
class UsuarioFactory extends Factory
{
    protected $model = Usuario::class;

    protected static ?string $senha;

    public function definition(): array
    {
        return [
            'clinica_id' => Clinica::factory(),
            'nome' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verificado_em' => now(),
            'senha' => static::$senha ??= Hash::make('password'),
            'perfil' => PerfilUsuario::Gerente,
            'ativo' => true,
            'remember_token' => Str::random(10),
        ];
    }

    public function administrador(): static
    {
        return $this->state(fn () => ['perfil' => PerfilUsuario::Administrador, 'clinica_id' => null]);
    }

    public function gerente(): static
    {
        return $this->state(fn () => ['perfil' => PerfilUsuario::Gerente]);
    }
}
