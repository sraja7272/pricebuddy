<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Enums\Icons;
use App\Filament\Resources\UserResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    public static function authorizeResourceAccess(): void
    {
        // Skip the resource-level viewAny check here. EditRecord::authorizeAccess()
        // already calls canEdit($record) which enforces the update policy — admins can
        // edit anyone, non-admins can only edit their own record. That is sufficient.
        abort_unless(auth()->check(), 403);
    }

    public static function canAccess(array $parameters = []): bool
    {
        $user = auth()->user();
        if ($user?->isAdmin()) {
            return true;
        }
        // Non-admins may only reach their own edit page.
        // $parameters['record'] is the resolved Eloquent model, so use getKey().
        $record = $parameters['record'] ?? null;
        if ($record === null) {
            return false;
        }
        $recordId = $record instanceof \Illuminate\Database\Eloquent\Model ? $record->getKey() : $record;

        return (int) $recordId === (int) $user?->id;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()->icon(Icons::Delete->value)->visible(fn () => (bool) auth()->user()?->isAdmin()),
        ];
    }
}
