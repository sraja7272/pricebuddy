<?php

namespace Tests\Unit\Enums;

use App\Enums\NotificationMethods;
use NotificationChannels\WebPush\WebPushChannel;
use Tests\TestCase;

class NotificationMethodsTest extends TestCase
{
    public function test_webpush_channel_resolves_to_web_push_channel_class(): void
    {
        $this->assertSame(WebPushChannel::class, NotificationMethods::WebPush->getChannel());
    }

    public function test_webpush_requires_user_settings(): void
    {
        $this->assertTrue(NotificationMethods::WebPush->requiresUserSettings());
    }

    public function test_webpush_has_correct_string_value(): void
    {
        $this->assertSame('webpush', NotificationMethods::WebPush->value);
    }
}
