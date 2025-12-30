<?php

use Illuminate\Support\Facades\Broadcast;
use App\Models\User;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels that your
| application supports. The given channel authorization callbacks are
| used to check if an authenticated user can listen to the channel.
|
*/

Broadcast::channel('user.{userId}', function (User $user, int $userId) {
    \Log::info('Channel auth attempt', [
        'user_id' => $user->id ?? 'null',
        'requested_userId' => $userId,
        'authenticated' => auth()->check()
    ]);
    return (int) $user->id === (int) $userId;
});
