<?php

namespace App\Listeners;

use App\Events\PriceCreatedEvent;
use App\Notifications\PriceAlertNotification;
use Exception;

class PriceCreatedListener
{
    public function __construct() {}

    public function handle(PriceCreatedEvent $event): void
    {
        // Need a product proceed.
        if (! $product = $event->price->product) {
            return;
        }

        // Need a url proceed.
        if (! $url = $event->price->url) {
            return;
        }

        if ($url->getAvailabilityStatus() !== null) {
            return;
        }

        try {
            // Notify all users with access if the price is within the range.
            if ($product->shouldNotifyOnPrice($event->price) && $url->shouldNotifyOnPrice($event->price)) {
                $previousMin = $url->prices()
                    ->where('id', '!=', $event->price->id)
                    ->whereNotNull('price')
                    ->min('price');

                $isLowestEver = $previousMin !== null && $event->price->price < $previousMin;

                $product->usersWithAccess()->each(
                    fn ($user) => $user->notify(new PriceAlertNotification($url, isLowestEver: $isLowestEver))
                );
                $event->price->update(['notified' => true]);
            }
        } catch (Exception $e) {
            // Log the error.
            logger()->error('Error sending price alert notification: '.$e->getMessage(), [
                'product' => $product->title,
                'product_id' => $product->getKey(),
                'url' => $event->price->url,
                'url_id' => $event->price->getKey(),
            ]);
        }
    }
}
