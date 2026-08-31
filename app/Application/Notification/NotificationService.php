<?php

namespace App\Application\Notification;

use App\Infrastructure\Notification\DatabaseMessageNotification;
use App\Models\User;

class NotificationService
{
    public function send(User $user, string $type, array $data = []): void
    {
        $user->notify(new DatabaseMessageNotification($type, $data));
    }
}
