<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class NotificationsTest extends TestCase
{
    use RefreshDatabase;

    protected function migrateFreshUsing(): array
    {
        return ['--path' => [realpath(base_path('../web/database/migrations'))], '--realpath' => true];
    }

    private function records(): array
    {
        $owner = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $this->actingAs($owner);
        $customer = User::factory()->create(['role' => 'customer', 'status' => 'active']);
        $category = DB::table('categories')->insertGetId(['name' => 'Electronics', 'slug' => 'electronics', 'status' => 'active']);
        $product = DB::table('products')->insertGetId(['category_id' => $category, 'name' => 'Stock phone', 'slug' => 'stock-phone', 'price' => 100, 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $draft = DB::table('products')->insertGetId(['category_id' => $category, 'name' => 'Draft camera', 'slug' => 'draft-camera', 'price' => 50, 'status' => 'draft', 'created_at' => now(), 'updated_at' => now()]);
        $variant = DB::table('product_variants')->insertGetId(['product_id' => $product, 'name' => 'Black', 'sku' => 'PHONE-LOW', 'price' => 100, 'stock_quantity' => 2, 'reserved_quantity' => 1, 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $address = DB::table('addresses')->insertGetId(['user_id' => $customer->id, 'full_name' => 'Customer', 'phone' => '123', 'province' => 'Phnom Penh', 'street_address' => 'Street 1']);
        $base = ['user_id' => $customer->id, 'address_id' => $address, 'subtotal' => 100, 'total_amount' => 100, 'created_at' => now(), 'updated_at' => now()];
        $order = DB::table('orders')->insertGetId($base + ['order_number' => 'PENDING-ORDER', 'order_status' => 'pending', 'payment_status' => 'unpaid']);
        $paid = DB::table('orders')->insertGetId($base + ['order_number' => 'PAID-ORDER', 'order_status' => 'delivered', 'payment_status' => 'paid']);
        DB::table('payments')->insert(['order_id' => $order, 'user_id' => $customer->id, 'payment_method' => 'card', 'amount' => 100, 'currency' => 'USD', 'status' => 'failed', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('refund_requests')->insert(['order_id' => $paid, 'requested_by' => $owner->id, 'amount' => 10, 'reason' => 'Return', 'status' => 'requested', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('reviews')->insert(['user_id' => $customer->id, 'product_id' => $product, 'order_id' => $paid, 'rating' => 4, 'comment' => 'Pending review', 'status' => 'pending', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('inventory_reservations')->insert(['product_variant_id' => $variant, 'reference' => 'expired-checkout', 'quantity' => 1, 'status' => 'pending', 'expires_at' => now()->subMinutes(5), 'created_at' => now()->subMinutes(20), 'updated_at' => now()]);
        DB::table('activity_logs')->insert(['user_id' => $owner->id, 'action' => 'post api/v1/admin/products', 'created_at' => now()]);

        return compact('owner', 'category', 'product', 'draft', 'variant', 'order');
    }

    public function test_notifications_show_real_alerts_actions_and_matching_bell_count(): void
    {
        $this->records();
        $this->get('/notifications')->assertOk()->assertSee('Draft camera')->assertSee('PHONE-LOW')->assertSee('Payment failed')->assertSee('Review awaiting moderation')
            ->assertViewHas('notificationSummary', fn ($counts) => $counts['all'] === 7 && $counts['inventory'] === 2 && $counts['payments'] === 2);
        $this->get('/settings/account')->assertOk()->assertSee('View all notifications')->assertSee('aria-label="System notifications"', false);
        $this->get('/notifications?type=orders')->assertOk()->assertViewHas('items', fn ($items) => $items->total() === 1)->assertSee('PENDING-ORDER');
        $this->get('/notifications?type=activity')->assertOk()->assertSee('post api/v1/admin/products');
        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->assertDatabaseCount('activity_logs', 1);
    }

    public function test_alerts_disappear_when_the_underlying_issue_is_resolved(): void
    {
        $ids = $this->records();
        DB::table('products')->where('id', $ids['draft'])->update(['status' => 'active']);
        DB::table('product_variants')->where('id', $ids['variant'])->update(['stock_quantity' => 20, 'reserved_quantity' => 0]);
        DB::table('inventory_reservations')->update(['status' => 'released']);
        DB::table('orders')->where('id', $ids['order'])->update(['order_status' => 'confirmed', 'payment_status' => 'paid']);
        DB::table('payments')->update(['status' => 'paid']);
        DB::table('refund_requests')->update(['status' => 'approved']);
        DB::table('reviews')->update(['status' => 'approved']);
        $this->get('/notifications')->assertOk()->assertViewHas('notificationSummary', fn ($counts) => $counts['all'] === 0)->assertSee('You’re all caught up');
    }

    public function test_permissions_filter_alerts_and_protect_system_activity(): void
    {
        $this->records();
        $this->actingAs(User::factory()->create(['role' => 'admin', 'status' => 'active', 'admin_permissions' => ['catalog']]));
        $this->get('/notifications')->assertOk()->assertSee('Draft camera')->assertDontSee('PHONE-LOW')->assertDontSee('Payment failed')->assertViewHas('notificationSummary', fn ($counts) => $counts['all'] === 1);
        $this->get('/notifications?type=payments')->assertForbidden();
        $this->get('/notifications?type=activity')->assertForbidden();
        $this->get('/notifications?type=not-a-category')->assertSessionHasErrors('type');
        $this->actingAs(User::factory()->create(['role' => 'customer', 'status' => 'active']))->get('/notifications')->assertRedirect('/login');
    }

    public function test_alerts_are_paginated_and_keep_the_selected_filter(): void
    {
        $ids = $this->records();
        for ($i = 0; $i < 25; $i++) {
            DB::table('products')->insert(['category_id' => $ids['category'], 'name' => 'Draft '.$i, 'slug' => 'draft-'.$i, 'price' => 1, 'status' => 'draft', 'created_at' => now(), 'updated_at' => now()]);
        }
        $this->get('/notifications?type=catalog&page=2')->assertOk()->assertViewHas('items', fn ($items) => $items->total() === 26 && $items->count() === 6 && str_contains($items->previousPageUrl(), 'type=catalog'));
    }

    public function test_guests_cannot_open_notifications(): void
    {
        $this->get('/notifications')->assertRedirect('/login');
    }
}
