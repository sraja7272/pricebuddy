<?php

namespace App\Filament\Resources\ProductResource\Actions;

use App\Models\Product;
use Filament\Tables\Actions\Action;

class LeaveShareAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'leave_share';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label(__('Leave'));

        $this->icon('heroicon-o-arrow-right-on-rectangle');

        $this->color('danger');

        $this->requiresConfirmation();

        $this->modalHeading(__('Leave shared product'));

        $this->modalDescription(__('You will no longer have access to this product. The owner and any other shared users will still see it.'));

        $this->modalSubmitActionLabel(__('Leave'));

        // Visible only to shared recipients (not the owner).
        $this->visible(fn (Product $record): bool => auth()->id() !== $record->user_id);

        // Policy: any user with view access can remove themselves.
        $this->authorize(fn (Product $record): bool => auth()->user()->can('view', $record)
            && auth()->id() !== $record->user_id);

        $this->action(function (Product $record): void {
            // Only ever detaches the currently authenticated user — never anyone else.
            $record->sharedWith()->detach(auth()->id());

            $this->success();
        });

        $this->successNotificationTitle(__('You have left the shared product'));
    }
}
