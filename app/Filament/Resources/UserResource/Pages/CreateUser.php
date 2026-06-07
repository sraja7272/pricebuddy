<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use Filament\Resources\Pages\CreateRecord;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Honour the toggle only when an admin submitted the form.
        // Non-admins cannot reach this page, but belt-and-suspenders.
        $data['is_admin'] = auth()->user()?->is_admin
            ? (bool) ($data['is_admin'] ?? false)
            : false;

        return $data;
    }

    protected function afterCreate(): void
    {
        if (auth()->user()?->is_admin && isset($this->data['is_admin'])) {
            $this->record->forceFill(['is_admin' => (bool) $this->data['is_admin']])->save();
        }
    }
}
