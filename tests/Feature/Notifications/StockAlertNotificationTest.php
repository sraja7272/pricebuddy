<?php

namespace Tests\Feature\Notifications;

use App\Models\Url;
use App\Notifications\StockAlertNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use NotificationChannels\WebPush\WebPushMessage;
use Tests\TestCase;

class StockAlertNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_to_web_push_returns_a_web_push_message(): void
    {
        $url = Url::factory()->create();
        $notification = new StockAlertNotification($url);

        $message = $notification->toWebPush(new \stdClass, $notification);

        $this->assertInstanceOf(WebPushMessage::class, $message);
    }

    public function test_to_web_push_title_contains_back_in_stock(): void
    {
        $url = Url::factory()->create();
        $notification = new StockAlertNotification($url);

        $message = $notification->toWebPush(new \stdClass, $notification);

        $this->assertStringContainsString('Back in stock', $message->toArray()['title']);
    }
}
