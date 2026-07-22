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
use App\Http\Controllers\UserController;

// Public
Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
Route::post('/reset-password', [AuthController::class, 'resetPassword']);
Route::post('/verify-otp', [AuthController::class, 'verifyOtp']);

// Callback Midtrans
Route::post('/payments/notification', [PaymentController::class, 'notification']);
Route::get('/payments/success', function () {
    return response()->json([
        'message' => 'Payment success'
    ]);
});

Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
});

Route::middleware('auth:sanctum')->group(function () {

    // Auth
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/profile', [ProfileController::class, 'show']);
    Route::get('/user/{id}/profile', [ProfileController::class, 'getPublicProfile']);
    Route::put('/profile', [ProfileController::class, 'update']);
    Route::post('/fcm-token', [ProfileController::class, 'saveFcmToken']);
    Route::get('/menus', [MenuController::class, 'index']);
    Route::get('/menus/{menu}', [MenuController::class, 'show']);
    Route::get('/transactions', [TransactionController::class, 'index']);
    Route::get('/transactions/{transaction}', [TransactionController::class, 'show']);
    Route::get('/transactions/{transaction}/verify-status', [PaymentController::class, 'verifyStatus']);
    Route::get('/chats', [ChatController::class, 'index']);
    Route::post('/chats', [ChatController::class, 'store']);
    Route::post('/chats/send', [ChatController::class, 'send']);
    Route::get('/chat-rooms', [ChatController::class, 'rooms']);
    Route::post('/chats/mark-read/{senderId}', [ChatController::class, 'markAsRead']);
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::get('/notifications/{notification}', [NotificationController::class, 'show']);
    Route::patch('/notifications/{id}/read', [NotificationController::class, 'read']);
    Route::apiResource('ratings', RatingController::class)
        ->only(['index', 'store', 'show', 'update', 'destroy']);

    // OWNER & KARYAWAN
    Route::middleware('role:owner,karyawan')->group(function () {
        Route::apiResource('menus', MenuController::class)
            ->except(['index', 'show']);
        Route::post('/menus/{menu}', [MenuController::class, 'update']);
        Route::post('/transactions/offline', [TransactionController::class, 'storeOffline']);
        Route::put('/transactions/{transaction}', [TransactionController::class, 'update']);
        Route::post('/transactions/{transaction}/accept', [TransactionController::class, 'accept']);
        Route::put('/transactions/{transaction}/ready', [TransactionController::class, 'ready']);
        Route::post('/transactions/{transaction}/paid', [TransactionController::class, 'paid']);
        Route::post('/transactions/{transaction}/owner-cancel', [TransactionController::class, 'ownerCancel']);
        Route::get('/owner/transactions/{transaction}', [TransactionController::class, 'show']);
        Route::get('/owner/chat-rooms', [ChatController::class, 'chatRooms']);
        Route::post('/notifications', [NotificationController::class, 'store']);
    });

    // OWNER
    Route::middleware('role:owner')->group(function () {
        Route::get('/users', [UserController::class, 'index']);
        Route::put('/users/{id}/role', [UserController::class, 'updateRole']);
        Route::delete('/menus/{menu}', [MenuController::class, 'destroy']);
        Route::delete('/transactions/{transaction}', [TransactionController::class, 'destroy']);
        Route::get('/reports', [ReportController::class, 'index']);
        Route::get('/reports/pdf', [ReportController::class, 'exportPdf']);
        Route::delete('/notifications/{notification}', [NotificationController::class, 'destroy']);
    });

    // CUSTOMER
    Route::middleware('role:customer')->group(function () {
        Route::get('/cart', [CartController::class, 'index']);
        Route::post('/cart/items', [CartController::class, 'store']);
        Route::patch('/cart/items/{cartItem}', [CartController::class, 'update']);
        Route::delete('/cart/items/{cartItem}', [CartController::class, 'destroy']);
        Route::post('/cart/checkout', [CartController::class, 'checkout']);

        Route::get('/payments', [PaymentController::class, 'index']);
        Route::get('/payments/{payment}', [PaymentController::class, 'show']);
        Route::post('/payments/{transaction}/snap', [PaymentController::class, 'snap']);

        Route::post('/transactions', [TransactionController::class, 'store']);
        Route::post('/transactions/{transaction}/cancel', [TransactionController::class, 'cancel']);
    });
});
