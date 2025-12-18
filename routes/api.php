<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\SessionController;
use App\Http\Controllers\Api\PhotoController;
use App\Http\Controllers\Api\CollageController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\DeliveryController;


    // Session routes
    Route::post('/session/start', [SessionController::class, 'start']);
    Route::post('/session/end', [SessionController::class, 'end']);

    // Photo routes
    Route::post('/photo/upload', [PhotoController::class, 'upload']);

    // Collage routes
    Route::get('/collages', [CollageController::class, 'index']);
    Route::get('/collage/{id}', [CollageController::class, 'show']);

    // Order routes
    Route::post('/order', [OrderController::class, 'store']);
    Route::get('/order/{id}', [OrderController::class, 'show']);

    // Payment routes
    Route::post('/payment/init', [PaymentController::class, 'init']);
    Route::post('/payment/webhook', [PaymentController::class, 'webhook']);

    // Delivery routes
    Route::post('/order/{id}/delivery/telegram', [DeliveryController::class, 'telegram']);
    Route::post('/order/{id}/delivery/email', [DeliveryController::class, 'email']);
    Route::post('/order/{id}/delivery/print', [DeliveryController::class, 'print']);

