<?php

namespace Database\Seeders;

use App\Services\CustomerAnnouncements;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CustomerNotificationDemoSeeder extends Seeder
{
    public function run(): void
    {
        $messages = [
            ['Welcome to E24', 'Sample notification: your store updates will appear here. You can mark messages as read at any time.', 'home'],
            ['Explore the collection', 'Sample notification: browse the latest products and find your next everyday favorite.', 'products'],
            ['Your account, in one place', 'Sample notification: manage your personal information and addresses from your profile.', 'profile'],
        ];
        foreach ($messages as $index => [$title, $message, $action]) {
            $id = 'e2400000-0000-4000-8000-'.str_pad((string) ($index + 1), 12, '0', STR_PAD_LEFT);
            DB::table('customer_notification_campaigns')->insertOrIgnore([
                'id' => $id, 'created_by' => null, 'request_key' => $id, 'audience' => 'all',
                'title' => $title, 'message' => $message, 'action' => $action,
                'upper_user_id' => DB::table('users')->max('id') ?? 0, 'status' => 'pending',
                'created_at' => now(), 'updated_at' => now(),
            ]);
            while (app(CustomerAnnouncements::class)->deliverBatch($id)) {
                // Use the same bounded backend delivery path without running unrelated mail jobs.
            }
        }
    }
}
