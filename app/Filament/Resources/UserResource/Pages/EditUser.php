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

    /**
     * The "Push Notifications (this device)" field has no corresponding form
     * component — it's a raw view that subscribes/unsubscribes directly via
     * its own endpoints — so the form's dehydrated state never carries a
     * `webpush` key. Since `settings` is saved as a plain array (full
     * replace, not a JSON merge), saving this form for any unrelated reason
     * would otherwise silently wipe out the user's push subscription state.
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $existingWebPush = data_get($this->getRecord()->getAttribute('settings'), 'notifications.webpush');

        if ($existingWebPush !== null) {
            data_set($data, 'settings.notifications.webpush', $existingWebPush);
        }

        return $data;
    }
}
