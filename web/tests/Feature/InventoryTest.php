<?php

namespace Tests\Feature;

use App\Models\ProductVariant;
use App\Models\User;
use App\Services\InventoryService;
use Database\Seeders\CatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class InventoryTest extends TestCase
{
    use RefreshDatabase;

    private function variant(): ProductVariant
    {
        $this->seed(CatalogSeeder::class);

        return ProductVariant::query()->firstOrFail();
    }

    public function test_receipt_is_audited_idempotent_and_rejects_changed_payload(): void
    {
        $variant = $this->variant();
        $stock = $variant->stock_quantity;
        $this->actingAs(User::factory()->create(['role' => 'admin']), 'sanctum');
        $url = "/api/v1/admin/inventory/{$variant->id}/adjustments";
        $payload = ['type' => 'receive', 'quantity' => 10, 'reason' => 'Supplier delivery', 'idempotency_key' => 'receipt-1'];
        $id = $this->postJson($url, $payload)->assertOk()->json('data.id');
        $this->postJson($url, $payload)->assertOk()->assertJsonPath('data.id', $id);
        $this->assertEquals($stock + 10, $variant->fresh()->stock_quantity);
        $this->postJson($url, array_replace($payload, ['quantity' => 11]))->assertUnprocessable();
        $this->getJson("/api/v1/admin/inventory/{$variant->id}/movements")->assertOk()->assertJsonPath('data.0.actor_id', auth()->id());
    }

    public function test_customers_guests_and_blocked_admins_cannot_change_inventory(): void
    {
        $this->getJson('/api/v1/admin/inventory')->assertUnauthorized();
        $this->actingAs(User::factory()->create(['role' => 'customer']), 'sanctum')->getJson('/api/v1/admin/inventory')->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => 'admin', 'status' => 'blocked']), 'sanctum')->getJson('/api/v1/admin/inventory')->assertForbidden();
    }

    public function test_reserve_commit_and_partial_return_are_idempotent(): void
    {
        $variant = $this->variant();
        $stock = $variant->stock_quantity;
        $inventory = app(InventoryService::class);
        $inventory->reserve($variant->id, 3, 'checkout-1');
        $inventory->reserve($variant->id, 3, 'checkout-1');
        $this->assertEquals(3, $variant->fresh()->reserved_quantity);
        $this->assertEquals($stock - 3, $variant->fresh()->available_stock);
        $inventory->commit($variant->id, 'checkout-1');
        $inventory->commit($variant->id, 'checkout-1');
        $this->assertEquals($stock - 3, $variant->fresh()->stock_quantity);
        $this->assertEquals(0, $variant->fresh()->reserved_quantity);
        $this->assertEquals(3, $variant->fresh()->sold_quantity);
        $inventory->returnStock($variant->id, 'checkout-1', 2, 'return-1', 'Customer return');
        $inventory->returnStock($variant->id, 'checkout-1', 2, 'return-1', 'Customer return');
        $this->assertEquals($stock - 1, $variant->fresh()->stock_quantity);
        $this->assertEquals(1, $variant->fresh()->sold_quantity);
        $this->expectException(ValidationException::class);
        $inventory->returnStock($variant->id, 'checkout-1', 2, 'return-2', 'Too many');
    }

    public function test_reservations_cannot_oversell_or_remove_reserved_stock(): void
    {
        $variant = $this->variant();
        $inventory = app(InventoryService::class);
        $inventory->reserve($variant->id, $variant->stock_quantity, 'all-stock');
        foreach (['reserve', 'adjust'] as $operation) {
            try {
                if ($operation === 'reserve') {
                    $inventory->reserve($variant->id, 1, 'another-checkout');
                } else {
                    $inventory->adjust($variant->id, -1, 'adjustment', 'Damage', 'damage-1');
                }
                $this->fail('Reserved stock was oversold or removed.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('inventory', $exception->errors());
            }
        }
        $this->assertEquals($variant->stock_quantity, $variant->fresh()->reserved_quantity);
    }

    public function test_release_and_expiry_restore_available_stock_once(): void
    {
        $variant = $this->variant();
        $inventory = app(InventoryService::class);
        $inventory->reserve($variant->id, 2, 'cancel');
        $inventory->release($variant->id, 'cancel');
        $inventory->release($variant->id, 'cancel');
        $inventory->reserve($variant->id, 2, 'timeout', 1);
        $this->travel(2)->minutes();
        $this->artisan('inventory:expire-reservations')->assertSuccessful();
        $this->artisan('inventory:expire-reservations')->assertSuccessful();
        $this->assertEquals(0, $variant->fresh()->reserved_quantity);
        $this->assertEquals($variant->stock_quantity, $variant->fresh()->stock_quantity);
        $this->assertDatabaseHas('inventory_reservations', ['reference' => 'timeout', 'status' => 'expired']);
        $this->expectException(ValidationException::class);
        $inventory->commit($variant->id, 'timeout');
    }

    public function test_product_metadata_update_preserves_stock_and_rejects_stock_edits(): void
    {
        $variant = $this->variant();
        app(InventoryService::class)->reserve($variant->id, 2, 'pending');
        $this->actingAs(User::factory()->create(['role' => 'admin']), 'sanctum');
        $payload = ['variants' => [[
            'id' => $variant->id, 'sku' => $variant->sku, 'name' => 'Updated variant',
            'price' => $variant->price, 'status' => 'active',
        ]]];
        $url = '/api/v1/admin/products/'.$variant->product_id;
        $this->patchJson($url, $payload)->assertOk();
        $this->assertEquals(2, $variant->fresh()->reserved_quantity);
        $this->assertEquals($variant->stock_quantity, $variant->fresh()->stock_quantity);
        $payload['variants'][0]['stock_quantity'] = 999;
        $this->patchJson($url, $payload)->assertUnprocessable();
        unset($payload['variants'][0]['stock_quantity']);
        $payload['variants'][0]['reserved_quantity'] = 0;
        $this->patchJson($url, $payload)->assertUnprocessable();
    }

    public function test_low_stock_filter_and_reseeding_preserve_balances(): void
    {
        $variant = $this->variant();
        app(InventoryService::class)->reserve($variant->id, $variant->stock_quantity, 'all');
        $this->seed(CatalogSeeder::class);
        $this->assertEquals($variant->stock_quantity, $variant->fresh()->reserved_quantity);
        $this->actingAs(User::factory()->create(['role' => 'admin']), 'sanctum')
            ->getJson('/api/v1/admin/inventory?low_stock=1')->assertOk()->assertJsonPath('data.0.id', $variant->id);
        $this->getJson('/api/v1/admin/inventory?per_page=0')->assertUnprocessable();
    }

    public function test_inventory_changes_roll_back_with_outer_checkout_transaction(): void
    {
        $variant = $this->variant();
        try {
            DB::transaction(function () use ($variant): void {
                app(InventoryService::class)->reserve($variant->id, 1, 'rollback');
                throw new \RuntimeException('Checkout failed');
            });
        } catch (\RuntimeException) {
            $this->assertEquals(0, $variant->fresh()->reserved_quantity);
        }
        $this->assertDatabaseMissing('inventory_reservations', ['reference' => 'rollback']);
        $this->assertDatabaseMissing('stock_movements', ['reference' => 'rollback']);
    }

    public function test_expired_reservation_cannot_be_committed_before_scheduler_runs(): void
    {
        $variant = $this->variant();
        $inventory = app(InventoryService::class);
        $inventory->reserve($variant->id, 1, 'late-payment', 1);
        $this->travel(2)->minutes();
        try {
            $inventory->commit($variant->id, 'late-payment');
            $this->fail('Expired reservation was committed.');
        } catch (ValidationException) {
            $this->assertEquals($variant->stock_quantity, $variant->fresh()->stock_quantity);
            $this->assertEquals(0, $variant->fresh()->sold_quantity);
        }
    }

    public function test_foreign_variant_id_cannot_be_used_in_product_update(): void
    {
        $variant = $this->variant();
        $other = ProductVariant::query()->where('product_id', '!=', $variant->product_id)->firstOrFail();
        $this->actingAs(User::factory()->create(['role' => 'admin']), 'sanctum')
            ->patchJson('/api/v1/admin/products/'.$variant->product_id, ['variants' => [[
                'id' => $other->id, 'sku' => $other->sku, 'name' => 'Wrong owner', 'price' => 10, 'status' => 'active',
            ]]])->assertUnprocessable();
        $this->assertEquals($other->product_id, $other->fresh()->product_id);
    }
}
