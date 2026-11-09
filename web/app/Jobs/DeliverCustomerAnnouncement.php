<?php

namespace App\Jobs;

use App\Services\CustomerAnnouncements;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Throwable;

class DeliverCustomerAnnouncement implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 30;

    public int $uniqueFor = 300;

    public array $backoff = [15, 60, 180];

    public function __construct(public string $campaignId)
    {
        $this->onQueue('customer-notifications');
    }

    public function uniqueId(): string
    {
        return $this->campaignId;
    }

    public function handle(CustomerAnnouncements $announcements): void
    {
        if ($announcements->deliverBatch($this->campaignId)) {
            $announcements->enqueue($this->campaignId);
        }
    }

    public function failed(?Throwable $exception): void
    {
        DB::table('customer_notification_campaigns')->where('id', $this->campaignId)->where('status', 'pending')->update(['status' => 'failed', 'updated_at' => now()]);
    }
}
