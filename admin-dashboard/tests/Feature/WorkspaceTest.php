<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class WorkspaceTest extends TestCase
{
    use RefreshDatabase;

    protected function migrateFreshUsing(): array
    {
        return ['--path' => [realpath(base_path('../web/database/migrations'))], '--realpath' => true];
    }

    private function signIn(): User
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $this->actingAs($admin);

        return $admin;
    }

    private function catalog(): array
    {
        $category = DB::table('categories')->insertGetId(['name' => 'Electronics', 'slug' => 'electronics', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $product = DB::table('products')->insertGetId(['category_id' => $category, 'name' => 'Test Phone', 'slug' => 'test-phone', 'sku' => 'PHONE', 'price' => 120, 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $variant = DB::table('product_variants')->insertGetId(['product_id' => $product, 'name' => 'Black', 'sku' => 'PHONE-BLACK', 'price' => 120, 'stock_quantity' => 3, 'reserved_quantity' => 1, 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);

        return compact('category', 'product', 'variant');
    }

    public function test_all_navigation_pages_render_with_real_catalog_and_alerts(): void
    {
        $this->signIn();
        $ids = $this->catalog();
        foreach (['/dashboard', '/inventory', '/products', '/products/create', '/orders', '/customers', '/reports', '/settings', '/products/'.$ids['product'].'/edit', '/inventory/'.$ids['variant'].'/history'] as $path) {
            $this->get($path)->assertOk()->assertSee('E24');
        }
        $this->get('/inventory?stock=low&search=PHONE')->assertOk()->assertSee('PHONE-BLACK');
        $this->get('/products?search=not-found')->assertOk()->assertViewHas('products', fn ($products) => $products->total() === 0);
        $this->get('/dashboard')->assertViewHas('availableStock', 2)->assertViewHas('productCount', 1)->assertSee('System notifications');
    }

    public function test_inventory_mutation_uses_backend_and_revokes_request_token(): void
    {
        config(['services.shop.url' => 'http://127.0.0.1:8000/api/v1']);
        $admin = $this->signIn();
        $ids = $this->catalog();
        Http::preventStrayRequests();
        Http::fake(['127.0.0.1:8000/api/v1/*' => Http::response(['data' => ['id' => 1]], 200)]);
        $key = (string) Str::uuid();
        $this->post('/inventory/'.$ids['variant'].'/adjust', ['type' => 'receive', 'quantity' => 5, 'reason' => 'Delivery', 'idempotency_key' => $key])->assertRedirect('/inventory/'.$ids['variant'].'/history')->assertSessionHas('status');
        Http::assertSent(fn ($request) => $request->url() === 'http://127.0.0.1:8000/api/v1/admin/inventory/'.$ids['variant'].'/adjustments' && $request->hasHeader('Authorization') && $request['quantity'] === 5 && $request['idempotency_key'] === $key);
        $this->assertSame(0, $admin->tokens()->count());
        $this->assertDatabaseHas('product_variants', ['id' => $ids['variant'], 'stock_quantity' => 3]);
    }

    public function test_backend_validation_is_shown_and_credential_is_revoked(): void
    {
        $admin = $this->signIn();
        Http::fake(['*' => Http::response(['errors' => ['inventory' => ['Cannot remove reserved stock.']]], 422)]);
        $this->from('/inventory')->post('/inventory/1/adjust', ['type' => 'adjustment', 'quantity' => -20, 'reason' => 'Count correction', 'idempotency_key' => (string) Str::uuid()])->assertRedirect('/inventory')->assertSessionHasErrors('inventory');
        $this->assertSame(0, $admin->tokens()->count());
    }

    public function test_network_failure_is_recoverable_and_does_not_leave_a_token(): void
    {
        $admin = $this->signIn();
        Http::fake(fn () => throw new ConnectionException('Unavailable'));
        $this->from('/inventory')->post('/inventory/1/adjust', ['type' => 'receive', 'quantity' => 1, 'reason' => 'Delivery', 'idempotency_key' => (string) Str::uuid()])->assertSessionHasErrors('operation');
        $this->assertSame(0, $admin->tokens()->count());
    }

    public function test_product_create_edit_archive_and_restore_forward_to_backend(): void
    {
        $this->signIn();
        $ids = $this->catalog();
        Http::fake(['*' => Http::response(['data' => ['id' => 2]], 200)]);
        $payload = ['name' => 'New Phone', 'sku' => 'NEW', 'category_id' => $ids['category'], 'price' => 150, 'status' => 'active', 'initial_stock' => 4];
        $this->post('/products', $payload)->assertRedirect('/products');
        Http::assertSent(fn ($request) => $request->method() === 'POST' && $request['variants'][0]['stock_quantity'] === 4);
        $this->put('/products/'.$ids['product'], $payload)->assertRedirect('/products');
        Http::assertSent(fn ($request) => $request->method() === 'PATCH' && ! isset($request['variants']) && ! isset($request['initial_stock']));
        $this->delete('/products/'.$ids['product'])->assertRedirect('/products');
        $this->patch('/products/'.$ids['product'].'/restore')->assertRedirect('/products');
        Http::assertSentCount(4);
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_date_filter_revenue_order_details_and_csv_use_real_orders(): void
    {
        $this->signIn();
        $customer = User::factory()->create(['role' => 'customer', 'status' => 'active']);
        $address = DB::table('addresses')->insertGetId(['user_id' => $customer->id, 'full_name' => 'Customer', 'phone' => '123', 'province' => 'Phnom Penh', 'street_address' => 'Street 1']);
        $base = ['user_id' => $customer->id, 'address_id' => $address, 'subtotal' => 100, 'total_amount' => 100, 'order_status' => 'confirmed', 'created_at' => '2026-10-20 10:00:00', 'updated_at' => now()];
        $order = DB::table('orders')->insertGetId($base + ['order_number' => 'ORDER-100', 'payment_status' => 'paid']);
        DB::table('orders')->insert($base + ['order_number' => '=UNSAFE', 'payment_status' => 'unpaid']);
        $this->get('/dashboard?start=2026-10-01&end=2026-10-31')->assertOk()->assertViewHas('revenue', fn ($value) => (float) $value === 100.0)->assertViewHas('orderCount', 2);
        $this->get('/orders/'.$order)->assertOk()->assertSee('ORDER-100');
        $this->get('/dashboard?start=2026-09-01&end=2026-09-30')->assertViewHas('orderCount', 0);
        $response = $this->get('/reports/export?start=2026-10-01&end=2026-10-31')->assertOk()->assertDownload('orders-2026-10-01-2026-10-31.csv');
        $this->assertStringContainsString("'=UNSAFE", $response->streamedContent());
        $this->get('/dashboard?start=2020-01-01&end=2026-10-31')->assertSessionHasErrors('start');
        $this->get('/dashboard?start=2026-10-31&end=2026-10-01')->assertSessionHasErrors('end');
    }

    public function test_unauthorized_users_cannot_read_or_write_workspace_data(): void
    {
        $this->post('/inventory/1/adjust', [])->assertRedirect('/login');
        $this->get('/reports/export')->assertRedirect('/login');
        $this->actingAs(User::factory()->create(['role' => 'customer', 'status' => 'active']))->get('/inventory')->assertRedirect('/login');
        Http::assertNothingSent();
    }
}
