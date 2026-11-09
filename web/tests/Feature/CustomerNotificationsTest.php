<?php

namespace Tests\Feature;

use App\Jobs\DeliverCustomerAnnouncement;
use App\Models\User;
use App\Notifications\CustomerAnnouncement;
use App\Services\CustomerAnnouncements;
use Database\Seeders\CustomerNotificationDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CustomerNotificationsTest extends TestCase
{
    use RefreshDatabase;

    private function customer(): User
    {
        return User::factory()->create(['role' => 'customer', 'status' => 'active']);
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin', 'status' => 'active']);
    }

    private function payload(array $changes = []): array
    {
        return $changes + ['request_key' => (string) Str::uuid(), 'title' => 'New collection', 'message' => 'Explore our latest products.', 'action' => 'products', 'audience' => 'all'];
    }

    private function send(User $admin, array $data): object
    {
        $campaign = app(CustomerAnnouncements::class)->create($admin, $data);
        while (app(CustomerAnnouncements::class)->deliverBatch($campaign->id)) {
        }

        return $campaign;
    }

    public function test_notification_feed_is_private_and_marks_only_owned_messages_read(): void
    {
        $first = $this->customer();
        $other = $this->customer();
        $this->send($this->admin(), $this->payload());
        $firstId = $first->notifications()->firstOrFail()->id;
        $otherId = $other->notifications()->firstOrFail()->id;
        $this->getJson('/notifications/feed')->assertUnauthorized();
        $this->actingAs($first, 'web')->getJson('/notifications/feed')->assertOk()->assertJsonPath('unread_count', 1)->assertJsonCount(1, 'items')->assertHeader('Cache-Control', 'no-store, private');
        $this->getJson('/notifications/feed?summary=1')->assertJsonMissingPath('items');
        $this->postJson('/notifications/'.$otherId.'/read')->assertNotFound();
        $this->postJson('/notifications/'.$firstId.'/read')->assertOk()->assertJsonPath('redirect', route('storefront.products.index'));
        $this->post('/notifications/read-all')->assertRedirect();
        $this->assertSame(1, $other->unreadNotifications()->count());
        $this->getJson('/notifications/feed')->assertJsonPath('unread_count', 0);
    }

    public function test_admin_api_authorizes_validates_and_records_delivery_requests(): void
    {
        Queue::fake();
        $customer = $this->customer();
        Sanctum::actingAs($customer, ['customer']);
        $this->postJson('/api/v1/admin/customer-notifications', $this->payload())->assertForbidden();
        $admin = $this->admin();
        $admin->forceFill(['admin_permissions' => ['catalog']])->save();
        Sanctum::actingAs($admin, ['admin']);
        $this->postJson('/api/v1/admin/customer-notifications', $this->payload())->assertForbidden();
        $admin->forceFill(['admin_permissions' => ['customers']])->save();
        $this->postJson('/api/v1/admin/customer-notifications', $this->payload(['action' => 'https://evil.example']))->assertUnprocessable();
        $this->postJson('/api/v1/admin/customer-notifications', $this->payload(['audience' => 'customer', 'recipient_id' => $admin->id]))->assertUnprocessable();
        $data = $this->payload(['audience' => 'customer', 'recipient_id' => $customer->id]);
        $response = $this->postJson('/api/v1/admin/customer-notifications', $data)->assertAccepted();
        $id = $response->json('id');
        $this->postJson('/api/v1/admin/customer-notifications', $data)->assertAccepted()->assertJsonPath('id', $id);
        $this->postJson('/api/v1/admin/customer-notifications', array_replace($data, ['title' => 'Changed']))->assertUnprocessable();
        $this->assertDatabaseCount('customer_notification_campaigns', 1);
        $this->assertDatabaseHas('activity_logs', ['user_id' => $admin->id, 'action' => 'post api/v1/admin/customer-notifications']);
    }

    public function test_batched_delivery_retries_do_not_duplicate_and_skip_ineligible_or_new_users(): void
    {
        $admin = $this->admin();
        User::factory()->count(205)->create(['role' => 'customer', 'status' => 'active']);
        $blocked = User::factory()->create(['role' => 'customer', 'status' => 'blocked']);
        $deleted = $this->customer();
        $deleted->forceFill(['deleted_at' => now()])->save();
        $campaign = app(CustomerAnnouncements::class)->create($admin, $this->payload());
        $new = $this->customer();
        $this->assertTrue(app(CustomerAnnouncements::class)->deliverBatch($campaign->id));
        $this->assertDatabaseCount('notifications', 200);
        $this->assertFalse(app(CustomerAnnouncements::class)->deliverBatch($campaign->id));
        $this->assertFalse(app(CustomerAnnouncements::class)->deliverBatch($campaign->id));
        $this->assertDatabaseCount('notifications', 205);
        $this->assertDatabaseHas('customer_notification_campaigns', ['id' => $campaign->id, 'status' => 'completed', 'delivered_count' => 205]);
        foreach ([$admin, $blocked, $deleted, $new] as $user) {
            $this->assertSame(0, $user->notifications()->count());
        }
    }

    public function test_single_recipient_delivery_does_not_reach_other_customers(): void
    {
        $customer = $this->customer();
        $other = $this->customer();
        $this->send($this->admin(), $this->payload(['audience' => 'customer', 'recipient_id' => $customer->id]));
        $this->assertSame(1, $customer->notifications()->count());
        $this->assertSame(0, $other->notifications()->count());
    }

    public function test_database_queue_worker_delivers_and_chains_multiple_batches(): void
    {
        config(['queue.default' => 'database']);
        $admin = $this->admin();
        User::factory()->count(201)->create(['role' => 'customer', 'status' => 'active']);
        $campaign = app(CustomerAnnouncements::class)->create($admin, $this->payload());
        app(CustomerAnnouncements::class)->enqueue($campaign->id);
        $this->assertDatabaseCount('jobs', 1);
        $this->artisan('queue:work', ['--queue' => 'customer-notifications', '--stop-when-empty' => true, '--sleep' => 0, '--tries' => 1])->assertSuccessful();
        $this->assertDatabaseCount('notifications', 201);
        $this->assertDatabaseCount('jobs', 0);
        $this->assertDatabaseHas('customer_notification_campaigns', ['id' => $campaign->id, 'status' => 'completed', 'delivered_count' => 201]);
    }

    public function test_deleted_recipient_is_never_reinterpreted_as_a_broadcast(): void
    {
        $target = $this->customer();
        $other = $this->customer();
        $campaign = app(CustomerAnnouncements::class)->create($this->admin(), $this->payload(['audience' => 'customer', 'recipient_id' => $target->id]));
        DB::table('users')->where('id', $target->id)->delete();
        app(CustomerAnnouncements::class)->deliverBatch($campaign->id);
        $this->assertSame(0, $other->notifications()->count());
    }

    public function test_notification_text_is_escaped_and_full_inbox_is_paginated(): void
    {
        $customer = $this->customer();
        $this->send($this->admin(), $this->payload(['title' => '<script>alert(1)</script>']));
        $this->actingAs($customer, 'web')->get('/notifications')->assertOk()->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)->assertDontSee('<script>alert(1)</script>', false);
        for ($index = 0; $index < 21; $index++) {
            $customer->notifyNow(new CustomerAnnouncement('Update '.$index, 'Message', 'home'));
        }
        $this->get('/notifications')->assertViewHas('notifications', fn ($page) => $page->count() === 20 && $page->total() === 22);
        $this->getJson('/notifications/feed')->assertJsonCount(8, 'items');
    }

    public function test_pending_scheduler_dispatches_to_dedicated_queue_and_failed_delivery_can_retry(): void
    {
        Queue::fake();
        $customer = $this->customer();
        $admin = $this->admin();
        $campaign = app(CustomerAnnouncements::class)->create($admin, $this->payload());
        $this->artisan('notifications:dispatch-pending')->assertSuccessful();
        Queue::assertPushedOn('customer-notifications', DeliverCustomerAnnouncement::class);
        (new DeliverCustomerAnnouncement($campaign->id))->failed(null);
        $this->assertDatabaseHas('customer_notification_campaigns', ['id' => $campaign->id, 'status' => 'failed']);
        Sanctum::actingAs($admin, ['admin']);
        $this->postJson('/api/v1/admin/customer-notifications/'.$campaign->id.'/retry')->assertAccepted();
        app(CustomerAnnouncements::class)->deliverBatch($campaign->id);
        $this->assertSame(1, $customer->notifications()->count());
    }

    public function test_demo_seeder_is_repeatable_without_changing_accounts_or_sending_email(): void
    {
        $customer = $this->customer();
        $admin = $this->admin();
        $this->seed(CustomerNotificationDemoSeeder::class);
        $this->seed(CustomerNotificationDemoSeeder::class);
        $this->assertSame(3, $customer->notifications()->count());
        $this->assertSame(0, $admin->notifications()->count());
        $this->assertDatabaseCount('users', 2);
    }
}
