<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class WorkspaceIntegrationTest extends TestCase
{
    use DatabaseMigrations;

    public function runDatabaseMigrations(): void
    {
        $this->refreshTestDatabase();
        $this->beforeApplicationDestroyed(function (): void {
            $this->artisan('migrate:rollback', $this->migrateFreshUsing());
            RefreshDatabaseState::$migrated = false;
        });
    }

    protected function setUp(): void
    {
        if (getenv('RUN_SHOP_INTEGRATION') !== '1' || getenv('DB_CONNECTION') !== 'pgsql' || ! str_starts_with((string) getenv('DB_DATABASE'), 'shop_admin_ui_test_')) {
            $this->markTestSkipped('Requires a running backend and isolated PostgreSQL shop_admin_ui_test_ database.');
        }
        parent::setUp();
    }

    protected function migrateFreshUsing(): array
    {
        return ['--path' => [realpath(base_path('../web/database/migrations'))], '--realpath' => true];
    }

    public function test_real_backend_inventory_and_product_operations_from_admin_forms(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $this->actingAs($admin);
        $category = DB::table('categories')->insertGetId(['name' => 'Electronics', 'slug' => 'electronics', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $product = DB::table('products')->insertGetId(['category_id' => $category, 'name' => 'Integration Phone', 'slug' => 'integration-phone', 'sku' => 'PHONE', 'price' => 120, 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $variant = DB::table('product_variants')->insertGetId(['product_id' => $product, 'name' => 'Black', 'sku' => 'PHONE-BLACK', 'price' => 120, 'stock_quantity' => 3, 'reserved_quantity' => 1, 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $payload = ['type' => 'receive', 'quantity' => 5, 'reason' => 'Integration delivery', 'idempotency_key' => (string) Str::uuid()];
        $this->post('/inventory/'.$variant.'/adjust', $payload)->assertRedirect('/inventory/'.$variant.'/history')->assertSessionHasNoErrors();
        $this->assertDatabaseHas('product_variants', ['id' => $variant, 'stock_quantity' => 8, 'reserved_quantity' => 1]);
        $this->post('/inventory/'.$variant.'/adjust', $payload)->assertSessionHasNoErrors();
        $this->assertDatabaseHas('product_variants', ['id' => $variant, 'stock_quantity' => 8]);
        $this->assertDatabaseCount('stock_movements', 1);
        $this->get('/inventory/'.$variant.'/history')->assertOk()->assertSee('Integration delivery');
        $this->post('/inventory/'.$variant.'/adjust', ['type' => 'adjustment', 'quantity' => -9, 'reason' => 'Invalid removal', 'idempotency_key' => (string) Str::uuid()])->assertSessionHasErrors('inventory');
        $this->assertDatabaseHas('product_variants', ['id' => $variant, 'stock_quantity' => 8]);
        $new = ['name' => 'UI Created', 'sku' => 'UI-NEW', 'category_id' => $category, 'price' => 99, 'status' => 'active', 'initial_stock' => 4];
        $this->post('/products', $new)->assertRedirect('/products')->assertSessionHasNoErrors();
        $created = DB::table('products')->where('sku', 'UI-NEW')->first();
        $this->assertNotNull($created);
        $this->assertDatabaseHas('product_variants', ['product_id' => $created->id, 'stock_quantity' => 4]);
        $originalSlug = $created->slug;
        $this->put('/products/'.$created->id, array_replace($new, ['name' => 'UI Updated', 'price' => 90]))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('products', ['id' => $created->id, 'name' => 'UI Updated', 'slug' => $originalSlug]);
        $this->delete('/products/'.$created->id)->assertSessionHasNoErrors();
        $this->assertNotNull(DB::table('products')->find($created->id)->deleted_at);
        $this->patch('/products/'.$created->id.'/restore')->assertSessionHasNoErrors();
        $this->assertDatabaseHas('products', ['id' => $created->id, 'deleted_at' => null, 'status' => 'inactive']);
        $this->post('/manage/brands', ['name' => 'UI Brand', 'status' => 'active'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('brands', ['name' => 'UI Brand']);
        $this->post('/manage/attributes', ['name' => 'Size'])->assertSessionHasNoErrors();
        $attribute = DB::table('attributes')->where('name', 'Size')->value('id');
        $this->post('/manage/attribute-values', ['attribute_id' => $attribute, 'value' => 'Large'])->assertSessionHasNoErrors();
        $value = DB::table('attribute_values')->where('attribute_id', $attribute)->value('id');
        $variantPayload = ['name' => 'Large', 'sku' => 'UI-LARGE', 'price' => 120, 'status' => 'active', 'low_stock_threshold' => 3, 'initial_stock' => 2, 'values' => [$attribute => $value]];
        $this->post('/products/'.$product.'/variants', $variantPayload)->assertSessionHasNoErrors();
        $newVariant = DB::table('product_variants')->where('sku', 'UI-LARGE')->first();
        $this->assertNotNull($newVariant);
        $this->assertDatabaseHas('product_variant_values', ['product_variant_id' => $newVariant->id, 'attribute_id' => $attribute, 'attribute_value_id' => $value]);
        $this->patch('/inventory/'.$newVariant->id.'/threshold', ['low_stock_threshold' => 8])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('product_variants', ['id' => $newVariant->id, 'low_stock_threshold' => 8]);
        $customer = User::factory()->create(['role' => 'customer', 'status' => 'active']);
        $address = DB::table('addresses')->insertGetId(['user_id' => $customer->id, 'full_name' => 'Customer', 'phone' => '123', 'province' => 'Phnom Penh', 'street_address' => 'Street 1']);
        $order = DB::table('orders')->insertGetId(['user_id' => $customer->id, 'address_id' => $address, 'order_number' => 'UI-ORDER', 'subtotal' => 100, 'total_amount' => 100, 'payment_status' => 'paid', 'order_status' => 'pending', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('order_items')->insert(['order_id' => $order, 'product_id' => $product, 'product_variant_id' => $newVariant->id, 'product_name' => 'Phone', 'sku' => 'UI-LARGE', 'quantity' => 1, 'price' => 100, 'total' => 100, 'created_at' => now(), 'updated_at' => now()]);
        $this->post('/orders/'.$order.'/transition', ['status' => 'confirmed', 'note' => 'Payment verified'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('product_variants', ['id' => $newVariant->id, 'stock_quantity' => 1]);
        $this->post('/orders/'.$order.'/transition', ['status' => 'cancelled', 'note' => 'Customer cancelled'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('product_variants', ['id' => $newVariant->id, 'stock_quantity' => 2]);
        $this->post('/refunds', ['order_id' => $order, 'amount' => 100, 'reason' => 'Paid cancellation'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('refund_requests', ['order_id' => $order, 'status' => 'requested']);
        $this->assertDatabaseHas('orders', ['id' => $order, 'payment_status' => 'paid']);
        $this->assertTrue(DB::table('activity_logs')->where('user_id', $admin->id)->exists());
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }
}
