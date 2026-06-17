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
            'endpoint' => ['required', 'string', 'url'],
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
