<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

class RecordNotification extends Notification
{
    public function __construct(public string $kind, public string $subject, public string $reference, public string $title) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return ['kind' => $this->kind, 'subject' => $this->subject, 'reference' => $this->reference, 'title' => $this->title];
    }
}
