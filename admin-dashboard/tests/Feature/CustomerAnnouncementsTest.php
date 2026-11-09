<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class CustomerAnnouncementsTest extends TestCase
{
    use RefreshDatabase;

    protected function migrateFreshUsing(): array
    {
        return ['--path' => [realpath(base_path('../web/database/migrations'))], '--realpath' => true];
    }

    public function test_customer_announcements_require_customer_management_permission(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active', 'admin_permissions' => ['catalog']]);
        $this->actingAs($admin)->get('/customer-notifications')->assertForbidden();
        $this->getJson('/customer-notifications/recipients?search=buyer')->assertForbidden();
        Http::preventStrayRequests();
        $this->post('/customer-notifications', [])->assertForbidden();
    }

    public function test_workspace_search_and_send_use_the_backend_without_sending_mail(): void
    {
        config(['services.shop.url' => 'http://127.0.0.1:8000/api/v1']);
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active', 'admin_permissions' => ['customers']]);
        $customer = User::factory()->create(['name' => 'Sample buyer', 'email' => 'buyer@example.com', 'role' => 'customer', 'status' => 'active']);
        User::factory()->create(['name' => 'Blocked buyer', 'role' => 'customer', 'status' => 'blocked']);
        $this->actingAs($admin)->get('/customer-notifications')->assertOk()->assertSee('Customer notifications')->assertSee('Sample buyer')->assertSee('customer-announcements.js')->assertDontSee('Blocked buyer');
        $this->getJson('/customer-notifications/recipients?search=BUYER@')->assertOk()->assertJsonCount(1, 'customers')->assertJsonPath('customers.0.id', $customer->id)->assertHeader('Cache-Control', 'no-store, private');
        Http::preventStrayRequests();
        Http::fake(['127.0.0.1:8000/api/v1/admin/customer-notifications' => Http::response(['id' => (string) Str::uuid(), 'status' => 'pending'], 202)]);
        $payload = ['request_key' => (string) Str::uuid(), 'audience' => 'customer', 'recipient_id' => $customer->id, 'title' => 'Collection', 'message' => 'Explore products.', 'action' => 'products'];
        $this->post('/customer-notifications', $payload)->assertRedirect(route('customer-announcements.index'))->assertSessionHas('status');
        Http::assertSent(fn ($request) => $request['recipient_id'] === $customer->id && $request['request_key'] === $payload['request_key'] && $request->hasHeader('Authorization'));
        $this->assertSame(0, $admin->tokens()->count());
        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_delivery_history_and_retry_use_the_existing_cursor(): void
    {
        config(['services.shop.url' => 'http://127.0.0.1:8000/api/v1']);
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $id = (string) Str::uuid();
        DB::table('customer_notification_campaigns')->insert(['id' => $id, 'created_by' => $admin->id, 'request_key' => (string) Str::uuid(), 'title' => 'Store update', 'message' => 'Message', 'action' => 'home', 'audience' => 'all', 'status' => 'failed', 'delivered_count' => 200, 'cursor_user_id' => 200, 'created_at' => now(), 'updated_at' => now()]);
        $this->actingAs($admin)->get('/customer-notifications')->assertOk()->assertSee('Store update')->assertSee('Failed')->assertSee('Retry delivery');
        Http::preventStrayRequests();
        Http::fake(['127.0.0.1:8000/api/v1/admin/customer-notifications/'.$id.'/retry' => Http::response(['message' => 'Retry requested'], 202)]);
        $this->post('/customer-notifications/'.$id.'/retry')->assertRedirect(route('customer-announcements.index'));
        $this->assertDatabaseHas('customer_notification_campaigns', ['id' => $id, 'cursor_user_id' => 200]);
    }
}
