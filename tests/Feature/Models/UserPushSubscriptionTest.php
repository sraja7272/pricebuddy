<?php

namespace Tests\Feature\Models;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserPushSubscriptionTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_store_a_push_subscription(): void
    {
        $user = User::factory()->create();

        $user->updatePushSubscription(
            'https://fcm.googleapis.com/fcm/send/test-endpoint',
            'BNPublicKey==',
            'AuthToken==',
            'aes128gcm'
        );

        $this->assertDatabaseHas('push_subscriptions', [
            'subscribable_type' => User::class,
            'subscribable_id'   => $user->id,
            'endpoint'          => 'https://fcm.googleapis.com/fcm/send/test-endpoint',
        ]);
    }

    public function test_user_can_delete_a_push_subscription(): void
    {
        $user = User::factory()->create();
        $endpoint = 'https://fcm.googleapis.com/fcm/send/test-endpoint';

        $user->updatePushSubscription($endpoint, 'BNPublicKey==', 'AuthToken==', 'aes128gcm');
        $user->deletePushSubscription($endpoint);

        $this->assertDatabaseMissing('push_subscriptions', ['endpoint' => $endpoint]);
    }

    public function test_route_notification_for_webpush_returns_subscriptions(): void
    {
        $user = User::factory()->create();
        $user->updatePushSubscription('https://test.endpoint/1', 'pub1', 'auth1', 'aes128gcm');
        $user->updatePushSubscription('https://test.endpoint/2', 'pub2', 'auth2', 'aes128gcm');

        $subscriptions = $user->routeNotificationForWebPush();

        $this->assertCount(2, $subscriptions);
    }
}
