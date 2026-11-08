<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use RuntimeException;

class CustomerVerifyEmail extends VerifyEmail implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 30;

    public array $backoff = [30, 120, 300];

    public function __construct()
    {
        $this->afterCommit();
    }

    public function shouldSend(object $notifiable, string $channel): bool
    {
        $eligible = ! $notifiable->hasVerifiedEmail() && $notifiable->role === 'customer' && $notifiable->status === 'active' && $notifiable->deleted_at === null;
        if ($eligible && config('mail.default') === 'log') {
            throw new RuntimeException('Configure a private mail transport before delivering customer verification links.');
        }

        return $eligible;
    }
}
