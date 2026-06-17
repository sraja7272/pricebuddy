<?php

use App\Http\Controllers\ManifestController;
use App\Http\Controllers\ProductController;
use Illuminate\Support\Facades\Route;

Route::get('manifest.json', ManifestController::class);

Route::get('/', function () {
    return redirect(route('filament.admin.pages.home-dashboard', [], false));
});

Route::prefix('admin/products')->name('filament.admin.resources.products.')
    ->group(function () {
        Route::post('/{product}/fetch', [ProductController::class, 'fetch'])
            ->name('fetch');
    });

Route::middleware('auth')->group(function () {
    Route::post('push/subscribe', [\App\Http\Controllers\PushSubscriptionController::class, 'subscribe'])
        ->name('push.subscribe');
    Route::delete('push/subscribe', [\App\Http\Controllers\PushSubscriptionController::class, 'unsubscribe'])
        ->name('push.unsubscribe');
    Route::get('push/config', [\App\Http\Controllers\PushSubscriptionController::class, 'config'])
        ->name('push.config');
});
