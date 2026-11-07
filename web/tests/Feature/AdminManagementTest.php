<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Database\Seeders\CatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminManagementTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): User
    {
        $owner = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $this->actingAs($owner, 'sanctum');

        return $owner;
    }

    private function order(ProductVariant $variant, string $payment = 'paid'): int
    {
        $customer = User::factory()->create(['role' => 'customer', 'status' => 'active']);
        $address = DB::table('addresses')->insertGetId(['user_id' => $customer->id, 'full_name' => 'Customer', 'phone' => '123', 'province' => 'Phnom Penh', 'street_address' => 'Street 1']);
        $id = DB::table('orders')->insertGetId(['user_id' => $customer->id, 'address_id' => $address, 'order_number' => 'ORD-'.uniqid(), 'subtotal' => 100, 'total_amount' => 100, 'payment_status' => $payment, 'order_status' => 'pending', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('order_items')->insert(['order_id' => $id, 'product_id' => $variant->product_id, 'product_variant_id' => $variant->id, 'product_name' => 'Test product', 'sku' => $variant->sku, 'quantity' => 2, 'price' => 50, 'total' => 100, 'created_at' => now(), 'updated_at' => now()]);

        return $id;
    }

    public function test_categories_archive_restore_and_cycles_are_protected(): void
    {
        $this->owner();
        $base = ['status' => 'active', 'sort_order' => 0];
        $root = $this->postJson('/api/v1/admin/manage/categories', $base + ['name' => 'Root'])->assertCreated()->json('data.id');
        $child = $this->postJson('/api/v1/admin/manage/categories', $base + ['name' => 'Child', 'parent_id' => $root])->assertCreated()->json('data.id');
        $this->patchJson('/api/v1/admin/manage/categories/'.$root, $base + ['name' => 'Root', 'parent_id' => $child])->assertUnprocessable();
        $this->patchJson('/api/v1/admin/manage/categories/'.$root, $base + ['name' => 'Root', 'parent_id' => (string) $root])->assertUnprocessable();
        $this->deleteJson('/api/v1/admin/manage/categories/'.$root)->assertUnprocessable();
        $this->deleteJson('/api/v1/admin/manage/categories/'.$child)->assertOk();
        $this->patchJson('/api/v1/admin/manage/categories/'.$child.'/restore')->assertOk();
        $this->assertDatabaseHas('categories', ['id' => $child, 'deleted_at' => null]);
        $this->assertTrue(DB::table('activity_logs')->where('entity_type', 'manage')->exists());
    }

    public function test_variants_keep_stock_and_validate_attribute_ownership(): void
    {
        $this->owner();
        $this->seed(CatalogSeeder::class);
        $product = Product::query()->firstOrFail();
        $attribute = $this->postJson('/api/v1/admin/manage/attributes', ['name' => 'Material'])->assertCreated()->json('data.id');
        $value = $this->postJson('/api/v1/admin/manage/attribute-values', ['attribute_id' => $attribute, 'value' => 'Cotton'])->assertCreated()->json('data.id');
        $payload = ['sku' => 'NEW-VARIANT', 'name' => 'Cotton model', 'price' => 99, 'status' => 'active', 'low_stock_threshold' => 3, 'initial_stock' => 6, 'values' => [['attribute_id' => $attribute, 'attribute_value_id' => $value]]];
        $id = $this->postJson('/api/v1/admin/products/'.$product->id.'/variants', $payload)->assertCreated()->json('data.id');
        $this->assertDatabaseHas('product_variants', ['id' => $id, 'stock_quantity' => 6]);
        unset($payload['initial_stock']);
        $payload['price'] = 89;
        $this->patchJson('/api/v1/admin/products/'.$product->id.'/variants/'.$id, $payload)->assertOk();
        $this->assertDatabaseHas('product_variants', ['id' => $id, 'stock_quantity' => 6, 'price' => 89]);
        $payload['values'][0]['attribute_id'] = 99999;
        $this->patchJson('/api/v1/admin/products/'.$product->id.'/variants/'.$id, $payload)->assertUnprocessable();
        $this->deleteJson('/api/v1/admin/manage/attribute-values/'.$value)->assertUnprocessable();
        $this->patchJson('/api/v1/admin/products/'.Product::query()->whereKeyNot($product->id)->firstOrFail()->id.'/variants/'.$id, $payload)->assertNotFound();
    }

    public function test_images_are_uploaded_with_primary_and_ownership_checks(): void
    {
        $this->owner();
        $this->seed(CatalogSeeder::class);
        Storage::fake('public');
        $product = Product::query()->firstOrFail();
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Wl6I54AAAAASUVORK5CYII=');
        $id = $this->postJson('/api/v1/admin/products/'.$product->id.'/images', ['image' => UploadedFile::fake()->createWithContent('product.png', $png), 'alt_text' => 'Product photograph'])->assertCreated()->json('data.id');
        $this->patchJson('/api/v1/admin/products/'.$product->id.'/images/'.$id)->assertOk();
        $this->assertEquals(1, DB::table('product_images')->where('product_id', $product->id)->where('is_primary', true)->count());
        $other = Product::query()->whereKeyNot($product->id)->firstOrFail();
        $this->deleteJson('/api/v1/admin/products/'.$other->id.'/images/'.$id)->assertNotFound();
        $this->postJson('/api/v1/admin/products/'.$product->id.'/images', ['image' => UploadedFile::fake()->createWithContent('script.svg', '<svg/>')])->assertUnprocessable();
        $this->deleteJson('/api/v1/admin/products/'.$product->id.'/images/'.$id)->assertOk();
    }

    public function test_order_confirmation_commits_stock_once_and_cancellation_restocks(): void
    {
        $this->owner();
        $this->seed(CatalogSeeder::class);
        $variant = ProductVariant::query()->firstOrFail();
        $before = $variant->stock_quantity;
        $order = $this->order($variant, 'unpaid');
        $url = '/api/v1/admin/orders/'.$order.'/transition';
        $this->patchJson($url, ['status' => 'confirmed', 'note' => 'Verify'])->assertUnprocessable();
        DB::table('orders')->where('id', $order)->update(['payment_status' => 'paid']);
        $this->patchJson($url, ['status' => 'confirmed', 'note' => 'Payment verified'])->assertOk();
        $this->patchJson($url, ['status' => 'confirmed', 'note' => 'Retry'])->assertOk();
        $this->assertEquals($before - 2, $variant->fresh()->stock_quantity);
        $this->patchJson($url, ['status' => 'cancelled', 'note' => 'Customer requested cancellation'])->assertOk();
        $this->assertEquals($before, $variant->fresh()->stock_quantity);
        $this->assertDatabaseHas('orders', ['id' => $order, 'payment_status' => 'paid', 'order_status' => 'cancelled']);
        $this->assertEquals(2, DB::table('order_status_histories')->where('order_id', $order)->count());
    }

    public function test_order_shipping_requires_valid_sequence_and_tracking(): void
    {
        $this->owner();
        $this->seed(CatalogSeeder::class);
        $order = $this->order(ProductVariant::query()->firstOrFail());
        $url = '/api/v1/admin/orders/'.$order.'/transition';
        $this->patchJson($url, ['status' => 'shipped', 'note' => 'Skip'])->assertUnprocessable();
        $this->patchJson($url, ['status' => 'confirmed', 'note' => 'Confirmed'])->assertOk();
        DB::table('orders')->where('id', $order)->update(['payment_status' => 'refunded']);
        $this->patchJson($url, ['status' => 'processing', 'note' => 'Refunded payment'])->assertUnprocessable();
        DB::table('orders')->where('id', $order)->update(['payment_status' => 'paid']);
        $this->patchJson($url, ['status' => 'processing', 'note' => 'Packing'])->assertOk();
        $this->patchJson($url, ['status' => 'shipped', 'note' => 'Sent'])->assertUnprocessable();
        $method = $this->postJson('/api/v1/admin/manage/shipping-methods', ['name' => 'Delivery', 'fee' => 2, 'status' => 'active'])->assertCreated()->json('data.id');
        $zone = ['name' => 'Central delivery', 'shipping_method_id' => $method, 'province' => 'Phnom Penh', 'fee' => 2, 'status' => 'active'];
        $zoneId = $this->postJson('/api/v1/admin/manage/shipping-zones', $zone)->assertCreated()->json('data.id');
        $this->patchJson('/api/v1/admin/manage/shipping-zones/'.$zoneId, array_replace($zone, ['fee' => 3]))->assertOk();
        $this->postJson('/api/v1/admin/manage/shipping-zones', array_replace($zone, ['shipping_method_id' => 999999]))->assertUnprocessable();
        $this->patchJson($url, ['status' => 'shipped', 'note' => 'Sent', 'shipping_method_id' => $method, 'carrier_name' => 'Courier', 'tracking_number' => 'TRACK-1'])->assertOk();
        $this->patchJson($url, ['status' => 'delivered', 'note' => 'Received'])->assertOk();
        $this->assertDatabaseHas('shipments', ['order_id' => $order, 'status' => 'delivered']);
    }

    public function test_refund_approval_is_capped_and_does_not_change_payment(): void
    {
        $this->owner();
        $this->seed(CatalogSeeder::class);
        $order = $this->order(ProductVariant::query()->firstOrFail());
        $id = $this->postJson('/api/v1/admin/refunds', ['order_id' => $order, 'amount' => 60, 'reason' => 'Partial return'])->assertCreated()->json('data.id');
        $this->postJson('/api/v1/admin/refunds', ['order_id' => $order, 'amount' => 50, 'reason' => 'Too much'])->assertUnprocessable();
        $this->patchJson('/api/v1/admin/refunds/'.$id, ['status' => 'approved', 'review_note' => 'Approved for gateway processing'])->assertOk();
        $this->assertDatabaseHas('orders', ['id' => $order, 'payment_status' => 'paid']);
        $this->patchJson('/api/v1/admin/refunds/'.$id, ['status' => 'rejected', 'review_note' => 'Already reviewed'])->assertUnprocessable();
    }

    public function test_permissions_customer_revocation_and_safe_staff_management(): void
    {
        $owner = $this->owner();
        $this->postJson('/api/v1/admin/staff', ['name' => 'Stock operator', 'email' => 'stock@example.com', 'password' => 'long-secret-password', 'password_confirmation' => 'long-secret-password', 'status' => 'active', 'admin_permissions' => ['inventory']])->assertCreated();
        $staff = User::query()->where('email', 'stock@example.com')->firstOrFail();
        $this->actingAs($staff, 'sanctum');
        $this->getJson('/api/v1/admin/inventory')->assertOk();
        $this->postJson('/api/v1/admin/manage/brands', ['name' => 'Forbidden', 'status' => 'active'])->assertForbidden();
        $this->postJson('/api/v1/admin/staff', [])->assertForbidden();
        $this->actingAs($owner, 'sanctum');
        $customer = User::factory()->create(['role' => 'customer', 'status' => 'active']);
        $customer->createToken('test');
        $this->patchJson('/api/v1/admin/customers/'.$customer->id.'/status', ['status' => 'blocked'])->assertOk();
        $this->assertEquals(0, $customer->tokens()->count());
        $this->patchJson('/api/v1/admin/staff/'.$owner->id, ['name' => $owner->name, 'email' => $owner->email, 'status' => 'active', 'admin_permissions' => ['inventory']])->assertUnprocessable();
        $this->assertStringNotContainsString('long-secret-password', json_encode(DB::table('activity_logs')->get()));
        $this->postJson('/api/v1/admin/manage/coupons', ['code' => 'BIG', 'type' => 'percentage', 'value' => 101, 'min_order_amount' => 0, 'status' => 'active'])->assertUnprocessable();
    }
}
