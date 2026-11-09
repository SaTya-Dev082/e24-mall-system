<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class CustomerAnnouncement extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $title, public string $message, public string $action = 'products')
    {
        $this->onQueue('customer-notifications')->afterCommit();
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function payload(): array
    {
        return ['title' => $this->title, 'message' => $this->message, 'action' => $this->action];
    }

    public function toArray(object $notifiable): array
    {
        return $this->payload();
    }
}
