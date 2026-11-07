<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;

class CustomerResetPassword extends ResetPassword implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(string $token)
    {
        parent::__construct($token);
        $this->afterCommit();
    }

    public function shouldSend(object $notifiable, string $channel): bool
    {
        return $notifiable->role === 'customer' && $notifiable->status === 'active' && $notifiable->deleted_at === null;
    }
}
