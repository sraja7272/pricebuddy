<?php

namespace App\Filament\Pages;

use Filament\Forms\Form;
use Filament\Http\Responses\Auth\Contracts\LoginResponse;
use Filament\Notifications\Notification;
use Filament\Pages\Auth\Login as BaseLogin;
use Illuminate\Validation\ValidationException;

class Login extends BaseLogin
{
    protected static string $layout = 'filament.layouts.auth';

    public function mount(): void
    {
        if ($this->isLocalLoginDisabled()
            && \Illuminate\Support\Facades\Route::has('socialite.filament.admin.oauth.redirect')) {
            redirect()->to(route('socialite.filament.admin.oauth.redirect', ['provider' => 'oidc']));

            return;
        }

        parent::mount();
    }

    public function form(Form $form): Form
    {
        if ($this->isLocalLoginDisabled()) {
            return $form->schema([]);
        }

        return parent::form($form);
    }

    public function authenticate(): ?LoginResponse
    {
        if ($this->isLocalLoginDisabled()) {
            Notification::make()
                ->title(__('Local login is disabled. Please use Single Sign-On.'))
                ->danger()
                ->send();

            throw ValidationException::withMessages([
                'data.email' => __('Local login is disabled. Please use Single Sign-On.'),
            ]);
        }

        return parent::authenticate();
    }

    protected function isLocalLoginDisabled(): bool
    {
        return filter_var(config('services.oidc.disable_local_login'), FILTER_VALIDATE_BOOLEAN);
    }
}
