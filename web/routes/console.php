<?php

use App\Services\InventoryService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inventory:expire-reservations', function (): void {
    $count = 0;
    DB::table('inventory_reservations')
        ->where('status', 'pending')->where('expires_at', '<=', now())
        ->orderBy('id')->chunkById(200, function ($reservations) use (&$count): void {
            foreach ($reservations as $reservation) {
                app(InventoryService::class)->expire($reservation->product_variant_id, $reservation->reference);
                $count++;
            }
        });
    $this->info("Processed {$count} expired reservations.");
})->purpose('Release expired inventory reservations safely');

Schedule::command('inventory:expire-reservations')->everyMinute()->withoutOverlapping();
Schedule::command('customers:deliver-verification')->everyMinute()->withoutOverlapping();
Schedule::command('notifications:dispatch-pending')->everyMinute()->withoutOverlapping();

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
