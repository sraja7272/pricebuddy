<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Enums\Icons;
use App\Filament\Resources\UserResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()->icon(Icons::Delete->value),
        ];
    }

    protected function afterSave(): void
    {
        if (auth()->user()?->is_admin) {
            $this->record->forceFill(['is_admin' => (bool) ($this->data['is_admin'] ?? false)])->save();
        }
    }
}
