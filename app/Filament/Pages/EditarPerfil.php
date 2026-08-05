<?php

namespace App\Filament\Pages;

use Filament\Auth\Pages\EditProfile;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rules\Password;
use SensitiveParameter;

/**
 * Tela nativa de "Editar perfil" do Filament, adaptada porque App\Models\Usuario usa colunas em
 * português (nome/senha) em vez de name/password. Os rótulos e mensagens continuam vindo das
 * traduções pt_BR já publicadas pelo próprio Filament — só os nomes dos campos mudam, pra baterem
 * com as colunas reais da tabela `usuarios`.
 */
class EditarPerfil extends EditProfile
{
    protected bool $senhaFoiAlterada = false;

    protected function getNameFormComponent(): Component
    {
        return TextInput::make('nome')
            ->label(__('filament-panels::auth/pages/edit-profile.form.name.label'))
            ->required()
            ->maxLength(255)
            ->autofocus();
    }

    protected function getPasswordFormComponent(): Component
    {
        return TextInput::make('senha')
            ->label(__('filament-panels::auth/pages/edit-profile.form.password.label'))
            ->validationAttribute(__('filament-panels::auth/pages/edit-profile.form.password.validation_attribute'))
            ->password()
            ->revealable(filament()->arePasswordsRevealable())
            ->rule(Password::default())
            ->showAllValidationMessages()
            ->autocomplete('new-password')
            ->dehydrated(fn (#[SensitiveParameter] ?string $state): bool => filled($state))
            ->live(debounce: 500)
            ->same('passwordConfirmation');
    }

    protected function getPasswordConfirmationFormComponent(): Component
    {
        return TextInput::make('passwordConfirmation')
            ->label(__('filament-panels::auth/pages/edit-profile.form.password_confirmation.label'))
            ->validationAttribute(__('filament-panels::auth/pages/edit-profile.form.password_confirmation.validation_attribute'))
            ->password()
            ->autocomplete('new-password')
            ->revealable(filament()->arePasswordsRevealable())
            ->required()
            ->visible(fn (Get $get): bool => filled($get('senha')))
            ->dehydrated(false);
    }

    protected function getCurrentPasswordFormComponent(): Component
    {
        return TextInput::make('currentPassword')
            ->label(__('filament-panels::auth/pages/edit-profile.form.current_password.label'))
            ->validationAttribute(__('filament-panels::auth/pages/edit-profile.form.current_password.validation_attribute'))
            ->belowContent(__('filament-panels::auth/pages/edit-profile.form.current_password.below_content'))
            ->password()
            ->autocomplete('current-password')
            ->currentPassword(guard: Filament::getAuthGuard())
            ->revealable(filament()->arePasswordsRevealable())
            ->required()
            ->visible(fn (Get $get): bool => filled($get('senha')) || ($get('email') !== $this->getUser()->getAttributeValue('email')))
            ->dehydrated(false);
    }

    /**
     * Mesmo mecanismo que o EditProfile nativo usa (ver Filament\Auth\Pages\EditProfile::save()),
     * só que lendo o hash já processado pelo cast 'hashed' do model em vez de $data['password']
     * (que aqui nem existe, porque o campo se chama 'senha'). Sem atualizar esse valor na sessão,
     * o AuthenticateSession derrubaria a sessão do próprio usuário logo depois dele trocar a senha,
     * já que o hash guardado ali ficaria desatualizado em relação ao novo hash no banco.
     */
    protected function handleRecordUpdate(Model $record, #[SensitiveParameter] array $data): Model
    {
        $this->senhaFoiAlterada = filled($data['senha'] ?? null);

        $record = parent::handleRecordUpdate($record, $data);

        if ($this->senhaFoiAlterada && request()->hasSession()) {
            request()->session()->put([
                'password_hash_' . Filament::getAuthGuard() => $record->getAuthPassword(),
            ]);
        }

        return $record;
    }

    /**
     * O EditProfile nativo limpa $this->data['password'] após salvar; como o campo aqui se chama
     * 'senha', precisamos limpar essa chave nós mesmos, senão o valor digitado continua visível
     * no campo depois de salvar.
     */
    protected function afterSave(): void
    {
        $this->data['senha'] = null;
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return $this->senhaFoiAlterada
            ? 'Senha alterada com sucesso.'
            : 'Perfil atualizado com sucesso.';
    }

    protected function getRedirectUrl(): ?string
    {
        return $this->senhaFoiAlterada ? Filament::getUrl() : null;
    }
}
