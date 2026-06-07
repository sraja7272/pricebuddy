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
        return filter_var(env('DISABLE_LOCAL_LOGIN', false), FILTER_VALIDATE_BOOLEAN);
    }
}
