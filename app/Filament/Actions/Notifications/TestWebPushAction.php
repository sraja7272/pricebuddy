<?php

namespace App\Filament\Actions\Notifications;

use App\Notifications\TestPushNotification;
use Filament\Forms\Components\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;

class TestWebPushAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'webpush_test';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label(__('Send test push'));

        $this->icon('heroicon-m-bell');

        $this->color('gray');

        $this->action(fn () => $this->testWebPushNotification());
    }

    protected function testWebPushNotification(): void
    {
        $user = Auth::user();

        if ($user->pushSubscriptions()->doesntExist()) {
            Notification::make()
                ->title('No push subscriptions found')
                ->body('Please install PriceBuddy as a PWA and enable notifications first')
                ->warning()
                ->send();

            return;
        }

        try {
            $user->notifyNow(new TestPushNotification);

            Notification::make()
                ->title('Test notification sent successfully')
                ->success()
                ->send();
        } catch (\Exception $e) {
            Notification::make()
                ->title('Failed to send test notification')
                ->body('Error: '.$e->getMessage())
                ->danger()
                ->send();
        }
    }
}
