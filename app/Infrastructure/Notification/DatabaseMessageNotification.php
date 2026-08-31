<?php

namespace App\Infrastructure\Notification;

use Illuminate\Notifications\Notification;

class DatabaseMessageNotification extends Notification
{
    public function __construct(
        private readonly string $notificationType,
        private readonly array $notificationData = [],
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => $this->notificationType,
            ...$this->notificationData,
        ];
    }
}
