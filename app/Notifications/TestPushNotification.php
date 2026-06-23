<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

class TestPushNotification extends Notification
{
    public function via(mixed $notifiable): array
    {
        return [WebPushChannel::class];
    }

    public function toWebPush(mixed $notifiable, mixed $notification): WebPushMessage
    {
        return (new WebPushMessage)
            ->title('PriceBuddy test notification')
            ->body('Push notifications are working!')
            ->icon('/images/icon.png')
            ->data(['url' => '/admin']);
    }
}
