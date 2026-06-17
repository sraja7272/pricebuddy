<?php

namespace Tests\Feature\Notifications;

use App\Enums\NotificationMethods;
use App\Models\Product;
use App\Models\User;
use App\Notifications\PriceAlertNotification;
use App\Services\Helpers\NotificationsHelper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;
use Tests\TestCase;

class PriceAlertNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_to_web_push_returns_a_web_push_message(): void
    {
        $product = Product::factory()->addUrlsAndPrices()->create();
        $url = $product->urls()->first();
        $notification = new PriceAlertNotification($url);

        $message = $notification->toWebPush(new \stdClass, $notification);

        $this->assertInstanceOf(WebPushMessage::class, $message);
    }

    public function test_to_web_push_title_contains_price_drop(): void
    {
        $product = Product::factory()->addUrlsAndPrices()->create();
        $url = $product->urls()->first();
        $notification = new PriceAlertNotification($url);

        $message = $notification->toWebPush(new \stdClass, $notification);

        $this->assertStringContainsString('Price drop', $message->toArray()['title']);
    }

    public function test_to_web_push_title_says_lowest_ever_when_flag_set(): void
    {
        $product = Product::factory()->addUrlsAndPrices()->create();
        $url = $product->urls()->first();
        $notification = new PriceAlertNotification($url, isLowestEver: true);

        $message = $notification->toWebPush(new \stdClass, $notification);

        $this->assertStringContainsString('Lowest ever', $message->toArray()['title']);
    }

    public function test_webpush_channel_included_in_via_when_enabled(): void
    {
        $user = User::factory()->withNotificationSettings([
            NotificationMethods::WebPush->value => ['enabled' => true],
        ])->createOne();

        NotificationsHelper::setSetting(NotificationMethods::WebPush, 'enabled', true);

        $product = Product::factory()->addUrlsAndPrices()->create();
        $url = $product->urls()->first();
        $notification = new PriceAlertNotification($url);

        $channels = $notification->via($user);

        $this->assertContains(WebPushChannel::class, $channels);
    }
}
