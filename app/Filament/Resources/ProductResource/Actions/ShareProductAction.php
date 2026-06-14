<?php

namespace App\Filament\Resources\ProductResource\Actions;

use App\Filament\Resources\ProductResource;
use App\Models\Product;
use App\Models\User;
use Filament\Forms\Components\Select;
use Filament\Notifications\Actions\Action as NotificationAction;
use Filament\Notifications\Notification;
use Filament\Tables\Actions\Action;

class ShareProductAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'share';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label(__('Share'));

        $this->icon('heroicon-o-user-plus');

        $this->color('gray');

        $this->modalHeading(__('Share product'));

        $this->modalDescription(__('Choose who can view and edit this product. Changes are visible to everyone with access.'));

        $this->authorize(fn (Product $record): bool => auth()->user()->can('share', $record));

        $this->form([
            Select::make('users')
                ->label(__('Share with'))
                ->multiple()
                ->searchable()
                ->preload()
                ->native(false)
                ->placeholder(__('Search by name or email...'))
                ->getSearchResultsUsing(fn (string $search): array => User::query()
                    ->whereKeyNot(auth()->id())
                    ->where(fn ($q) => $q
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%"))
                    ->orderBy('name')
                    ->limit(50)
                    ->get()
                    ->mapWithKeys(fn (User $user): array => [$user->getKey() => "{$user->name} ({$user->email})"])
                    ->all()
                )
                ->getOptionLabelsUsing(fn (array $values): array => User::whereIn('id', $values)
                    ->get()
                    ->mapWithKeys(fn (User $user): array => [$user->getKey() => "{$user->name} ({$user->email})"])
                    ->all()
                )
                ->default(fn (Product $record): array => $record->sharedWith->pluck('id')->all()),
        ]);

        $this->action(function (array $data, Product $record): void {
            $previousIds = $record->sharedWith()->pluck('users.id')->all();
            $newIds = array_map('intval', $data['users'] ?? []);

            $record->sharedWith()->sync($newIds);

            // Notify each newly-added recipient.
            $addedIds = array_values(array_diff($newIds, $previousIds));
            if (! empty($addedIds)) {
                User::whereIn('id', $addedIds)->each(function (User $user) use ($record): void {
                    Notification::make()
                        ->title(__('A product was shared with you'))
                        ->body($record->title)
                        ->icon('heroicon-o-shopping-cart')
                        ->actions([
                            NotificationAction::make('view')
                                ->label(__('View product'))
                                ->url(ProductResource::getUrl('view', ['record' => $record])),
                        ])
                        ->sendToDatabase($user);
                });
            }

            $this->success();
        });

        $this->successNotificationTitle(__('Share list updated'));
    }
}
