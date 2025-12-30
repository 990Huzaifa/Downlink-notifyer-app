<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SiteLinkController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\WebhookController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/
Route::post('signup', [AuthController::class, 'signup']);
Route::post('signin', [AuthController::class, 'signin']);
Route::post('social',[AuthController::class,'socialLoginSignup']);
Route::post('account-check',[AuthController::class,'accountCheck']);
Route::post('forgot-password', [AuthController::class, 'forgotPassword']);
Route::post('reset-password', [AuthController::class, 'resetPassword']);
Route::post('resend-code', [AuthController::class, 'resendCode']);

Route::get('/optimize-clear', function () {
    Artisan::call('optimize:clear');
    return 'Optimization cache cleared!';
});

Route::post('/broadcasting/auth', function (Illuminate\Http\Request $request) {
    $user = $request->user();
    
    \Log::info('Custom broadcasting auth', [
        'user_id' => $user->id,
        'channel' => $request->channel_name,
        'socket_id' => $request->socket_id
    ]);
    
    // Parse channel name to get userId
    // Format: "private-user.4" → extract "4"
    preg_match('/private-user\.(\d+)/', $request->channel_name, $matches);
    $requestedUserId = $matches[1] ?? null;
    
    \Log::info('Authorization check', [
        'authenticated_user' => $user->id,
        'requested_user' => $requestedUserId,
        'authorized' => $user->id == $requestedUserId
    ]);
    
    // Check if user is authorized for this channel
    if ($user->id != $requestedUserId) {
        return response()->json(['message' => 'Forbidden'], 403);
    }
    
    // Generate auth signature manually
    $channelName = $request->channel_name;
    $socketId = $request->socket_id;
    
    $pusherKey = config('broadcasting.connections.pusher.key');
    $pusherSecret = config('broadcasting.connections.pusher.secret');
    
    $stringToSign = $socketId . ':' . $channelName;
    $signature = hash_hmac('sha256', $stringToSign, $pusherSecret);
    $auth = $pusherKey . ':' . $signature;
    
    return response()->json([
        'auth' => $auth
    ]);
})->middleware('auth:sanctum');

Route::post('/webhook/apple', [WebhookController::class, 'handleApple']);
Route::post('/webhook/google', [WebhookController::class, 'handleGoogle']);

Route::middleware(['auth:sanctum'])->group(function () {

    Route::post('change-password', [AuthController::class, 'changePassword']);
    Route::get('/logout', [AuthController::class, 'logout']);

    Route::controller(SiteLinkController::class)->group(function () {
        Route::get('/site-link', 'index');
        Route::post('/site-link', 'store');
        Route::get('/site-link/{id}', 'show');
        Route::put('/site-link/{id}', 'update');
        Route::delete('/site-link/{id}', 'destroy');
        Route::put('/status/site-link/{id}', 'updateStatus');
        Route::get('/site-notify/{id}', 'notifyToggle');

        Route::get('enable-link/{id}', 'enableLink');
        
    });

    Route::controller(ProfileController::class)->group(function () {
        Route::get('/profile', 'profile');
        Route::post('/profile', 'updateProfile');
        Route::get('/check-plan', 'checkPlan');


    });

        
    Route::apiResource('notification', NotificationController::class)->only(['index']);
    Route::get('notification/mark-as-read/{id}', [NotificationController::class, 'markAsRead']);
    Route::delete('notification/{id}', [NotificationController::class, 'destroy']);

    Route::post('google/verify-payment', [PaymentController::class, 'verifyGoogle']);

        
});
