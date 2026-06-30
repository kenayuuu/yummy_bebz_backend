<?php

use App\Http\Controllers\ChatController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\MenuController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RatingController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\TransactionController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| AUTH
|--------------------------------------------------------------------------
*/

Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
});

/*
|--------------------------------------------------------------------------
| AUTHENTICATED ROUTES
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->group(function () {

    // AUTH
    Route::post('/auth/logout', [AuthController::class, 'logout']);

    // PROFILE
    Route::get('/profile', [ProfileController::class, 'show']);
    Route::put('/profile', [ProfileController::class, 'update']);

    // CHAT
    Route::get('/chats', [ChatController::class, 'index']);
    Route::post('/chats', [ChatController::class, 'store']);
    Route::post('/chats/send', [ChatController::class, 'send']);
    Route::get('/chat-rooms', [ChatController::class, 'rooms']);
    Route::post('/chats/mark-read/{senderId}', [ChatController::class, 'markAsRead']);

    // FCM TOKEN
    Route::post('/fcm-token', [ProfileController::class, 'saveFcmToken']);

    // MENUS (public after login)
    Route::get('/menus', [MenuController::class, 'index']);
    Route::get('/menus/{menu}', [MenuController::class, 'show']);

    // TRANSACTIONS
    Route::get('/transactions', [TransactionController::class, 'index']);
    Route::get('/transactions/{transaction}', [TransactionController::class, 'show']);

    // Notifications
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::patch('/notifications/{id}/read', [NotificationController::class, 'read']);

    // list chat room (akan kita pakai untuk owner/customer nanti)
    Route::get('/chat-rooms', [ChatController::class, 'rooms']);

    // get messages in 1 room
    Route::get('/chats', [ChatController::class, 'index']);

    // send message
    Route::post('/chats', [ChatController::class, 'send']);

    /*
    |--------------------------------------------------------------------------
    | NOTIFICATIONS
    |--------------------------------------------------------------------------
    */
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::get('/notifications/{notification}', [NotificationController::class, 'show']);
    Route::patch('/notifications/{notification}/read', [NotificationController::class, 'update']);

    Route::middleware('role:owner')->group(function () {

        Route::apiResource('menus', MenuController::class)
            ->except(['index', 'show']);

        Route::apiResource('transactions', TransactionController::class)
            ->except(['store']);

        Route::post('/notifications', [NotificationController::class, 'store']);
        Route::delete('/notifications/{notification}', [NotificationController::class, 'destroy']);

        Route::get('/reports', [ReportController::class, 'index']);

        Route::get('/reports/pdf', [ReportController::class, 'exportPdf']);

        // 🔥 OPTIONAL (kalau mau lebih clean chat owner)
        Route::get('/owner/chat-rooms', [ChatController::class, 'chatRooms']);
    });

    /*
    |--------------------------------------------------------------------------
    | CUSTOMER ONLY
    |--------------------------------------------------------------------------
    */
    Route::middleware('role:customer')->group(function () {

        // CART
        Route::get('/cart', [CartController::class, 'index']);
        Route::post('/cart/items', [CartController::class, 'store']);
        Route::patch('/cart/items/{cartItem}', [CartController::class, 'update']);
        Route::delete('/cart/items/{cartItem}', [CartController::class, 'destroy']);
        Route::post('/cart/checkout', [CartController::class, 'checkout']);

        // PAYMENTS
        Route::get('/payments', [PaymentController::class, 'index']);
        Route::get('/payments/{payment}', [PaymentController::class, 'show']);

        // TRANSACTION ACTION
        Route::post('/transactions/{transaction}/cancel', [TransactionController::class, 'cancel']);

        // RATINGS
        Route::apiResource('ratings', RatingController::class)
            ->only(['index', 'store', 'show', 'update', 'destroy']);
    });
});
