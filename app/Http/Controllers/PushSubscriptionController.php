<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class PushSubscriptionController extends Controller
{
    public function subscribe(Request $request): Response
    {
        $request->validate([
            'endpoint' => [
                'required', 'string', 'url',
                function (string $attr, string $value, \Closure $fail): void {
                    $host = parse_url($value, PHP_URL_HOST) ?: '';
                    $allowed = [
                        'fcm.googleapis.com',
                        'updates.push.services.mozilla.com',
                        'notify.windows.com',
                        'push.apple.com',
                    ];
                    $allowedSuffixes = ['.push.apple.com', '.notify.windows.com'];

                    foreach ($allowed as $domain) {
                        if ($host === $domain) {
                            return;
                        }
                    }
                    foreach ($allowedSuffixes as $suffix) {
                        if (str_ends_with($host, $suffix)) {
                            return;
                        }
                    }

                    $fail('The push endpoint host is not a recognised push service.');
                },
            ],
            'keys.p256dh' => ['required', 'string'],
            'keys.auth' => ['required', 'string'],
            'contentEncoding' => ['nullable', 'string'],
        ]);

        $user = $request->user();

        $user->updatePushSubscription(
            $request->input('endpoint'),
            $request->input('keys.p256dh'),
            $request->input('keys.auth'),
            $request->input('contentEncoding', 'aesgcm')
        );

        $settings = $user->settings ?? [];
        data_set($settings, 'notifications.webpush.enabled', true);
        $user->update(['settings' => $settings]);

        return response()->noContent();
    }

    public function unsubscribe(Request $request): Response
    {
        $request->validate([
            'endpoint' => ['required', 'string'],
        ]);

        $user = $request->user();

        $user->deletePushSubscription($request->input('endpoint'));

        if ($user->pushSubscriptions()->doesntExist()) {
            $settings = $user->settings ?? [];
            data_set($settings, 'notifications.webpush.enabled', false);
            $user->update(['settings' => $settings]);
        }

        return response()->noContent();
    }

    public function config(): JsonResponse
    {
        return response()->json([
            'vapidPublicKey' => config('webpush.vapid.public_key'),
        ]);
    }
}
