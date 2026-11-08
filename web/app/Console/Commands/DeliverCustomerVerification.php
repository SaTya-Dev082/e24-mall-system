<?php

namespace App\Console\Commands;

use App\Services\CustomerVerificationDelivery;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DeliverCustomerVerification extends Command
{
    protected $signature = 'customers:deliver-verification';

    protected $description = 'Retry pending customer verification notification enqueue attempts';

    public function handle(CustomerVerificationDelivery $delivery): int
    {
        DB::table('customer_verification_deliveries')->whereNull('queued_at')->where('available_at', '<=', now())
            ->orderBy('id')->chunkById(100, function ($requests) use ($delivery): void {
                foreach ($requests as $request) {
                    $delivery->deliver($request->user_id);
                }
            });

        return self::SUCCESS;
    }
}
