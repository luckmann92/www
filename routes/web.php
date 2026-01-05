<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\Fortify\Features;
use App\Http\Controllers\Api\KioskController;
use App\Http\Controllers\Api\SessionController;
use App\Http\Controllers\Api\PhotoController;
use App\Http\Controllers\Api\CollageController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\DeliveryController;
use App\Http\Controllers\Api\TestController;
use App\Http\Controllers\DebugController;

Route::get('/', [KioskController::class, 'index'])->name('home');

Route::get('dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::get('/debug', [DebugController::class, 'store']);

Route::prefix('api')->group(function () {
    Route::post('/image', [TestController::class, 'imageGeneration']);

    Route::post('/session/start', [SessionController::class, 'start']);
    Route::post('/session/end', [SessionController::class, 'end']);

    Route::post('/photo/upload', [PhotoController::class, 'upload']);

    Route::get('/collages', [CollageController::class, 'index']);
    Route::get('/collage/{id}', [CollageController::class, 'show']);

    Route::post('/order', [OrderController::class, 'store']);
    Route::get('/order/{id}', [OrderController::class, 'show']);

    Route::post('/payment/init', [PaymentController::class, 'init']);
    Route::post('/payment/webhook', [PaymentController::class, 'webhook'    ]);

    Route::post('/order/{id}/delivery/telegram', [DeliveryController::class, 'telegram']);
    Route::post('/order/{id}/delivery/email', [DeliveryController::class, 'email']);
    Route::post('/order/{id}/delivery/print', [DeliveryController::class, 'print']);
    Route::post('/order/{id}/telegram-qr', [App\Http\Controllers\Api\TelegramQrController::class, 'generateQr']);
});

require __DIR__.'/settings.php';
