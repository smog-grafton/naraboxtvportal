<?php

namespace App\Services;

use App\Models\UserNotification;

class UserNotificationService
{
    public function createGlobal(array $payload): UserNotification
    {
        return UserNotification::create(array_merge($payload, [
            'is_global' => true,
            'user_id' => null,
        ]));
    }

    public function createForUser(int $userId, array $payload): UserNotification
    {
        return UserNotification::create(array_merge($payload, [
            'user_id' => $userId,
            'is_global' => false,
        ]));
    }
}
