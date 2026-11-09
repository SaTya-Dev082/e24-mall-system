<?php

namespace App\Console\Commands;

use App\Services\CustomerAnnouncements;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DispatchCustomerAnnouncements extends Command
{
    protected $signature = 'notifications:dispatch-pending';

    protected $description = 'Enqueue pending customer announcements after a queue interruption';

    public function handle(CustomerAnnouncements $announcements): int
    {
        DB::table('customer_notification_campaigns')->where('status', 'pending')->orderBy('created_at')->limit(100)->get(['id'])
            ->each(fn ($campaign) => $announcements->enqueue($campaign->id));

        return self::SUCCESS;
    }
}
