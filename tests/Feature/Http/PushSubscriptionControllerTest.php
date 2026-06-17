<?php

namespace Tests\Feature\Http;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PushSubscriptionControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_subscribe_stores_subscription_and_enables_flag(): void
    {
        $this->actingAs($this->user);

        $response = $this->postJson('/push/subscribe', [
            'endpoint'        => 'https://fcm.googleapis.com/fcm/send/abc',
            'keys'            => [
                'p256dh' => 'BNPublicKey==',
                'auth'   => 'AuthToken==',
            ],
            'contentEncoding' => 'aes128gcm',
        ]);

        $response->assertNoContent();

        $this->assertDatabaseHas('push_subscriptions', [
            'subscribable_id'   => $this->user->id,
            'subscribable_type' => User::class,
            'endpoint'          => 'https://fcm.googleapis.com/fcm/send/abc',
        ]);

        $this->user->refresh();
        $this->assertTrue(
            (bool) data_get($this->user->settings, 'notifications.webpush.enabled')
        );
    }

    public function test_unsubscribe_deletes_subscription_and_clears_flag_when_none_remain(): void
    {
        $this->actingAs($this->user);
        $endpoint = 'https://fcm.googleapis.com/fcm/send/abc';
        $this->user->updatePushSubscription($endpoint, 'pub', 'auth', 'aes128gcm');

        $response = $this->deleteJson('/push/subscribe', ['endpoint' => $endpoint]);

        $response->assertNoContent();

        $this->assertDatabaseMissing('push_subscriptions', ['endpoint' => $endpoint]);

        $this->user->refresh();
        $this->assertFalse(
            (bool) data_get($this->user->settings, 'notifications.webpush.enabled')
        );
    }

    public function test_config_returns_vapid_public_key(): void
    {
        $this->actingAs($this->user);
        config(['webpush.vapid.public_key' => 'test-public-key']);

        $response = $this->getJson('/push/config');

        $response->assertOk()->assertJsonFragment(['vapidPublicKey' => 'test-public-key']);
    }

    public function test_unauthenticated_subscribe_is_rejected(): void
    {
        $response = $this->postJson('/push/subscribe', [
            'endpoint' => 'https://test.endpoint',
            'keys'     => ['p256dh' => 'pub', 'auth' => 'auth'],
        ]);

        $response->assertUnauthorized();
    }
}
