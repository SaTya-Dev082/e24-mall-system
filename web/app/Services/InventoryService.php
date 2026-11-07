<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventoryService
{
    public function adjust(int $variantId, int $quantity, string $type, string $reason, string $key, ?int $actorId = null): object
    {
        $this->ensure(in_array($type, ['receive', 'adjustment'], true), 'Invalid adjustment type.');
        $this->ensure($quantity !== 0 && abs($quantity) <= 1000000000, 'Invalid quantity.');
        $this->ensure($type !== 'receive' || $quantity > 0, 'Receipt quantity must be positive.');

        return DB::transaction(function () use ($variantId, $quantity, $type, $reason, $key, $actorId): object {
            $variant = $this->lockedVariant($variantId);
            $existing = DB::table('stock_movements')->where('product_variant_id', $variantId)->where('idempotency_key', 'admin:'.$key)->first();
            if ($existing) {
                $this->ensure($existing->type === $type && (int) $existing->quantity === $quantity && $existing->reason === $reason && (int) $existing->actor_id === (int) $actorId, 'Idempotency key was used with a different request.');

                return $existing;
            }
            $this->ensure($variant->stock_quantity + $quantity >= $variant->reserved_quantity, 'Cannot remove reserved stock or make stock negative.');
            $this->ensure($variant->stock_quantity + $quantity <= 2147483647, 'Stock exceeds supported limit.');

            return $this->record($variant, $type, $quantity, $quantity, 0, $reason, 'admin:'.$key, null, $actorId);
        }, 5);
    }

    public function reserve(int $variantId, int $quantity, string $reference, int $ttlMinutes = 15): object
    {
        $this->ensure($quantity > 0 && $quantity <= 1000000000 && $ttlMinutes >= 1 && $ttlMinutes <= 1440, 'Invalid reservation quantity or lifetime.');
        $this->ensure($reference !== '' && strlen($reference) <= 100, 'Invalid reservation reference.');

        return DB::transaction(function () use ($variantId, $quantity, $reference, $ttlMinutes): object {
            $variant = $this->lockedVariant($variantId);
            $this->ensure(! $variant->trashed(), 'Variant is unavailable.');
            $existing = DB::table('inventory_reservations')->where('product_variant_id', $variantId)->where('reference', $reference)->first();
            if ($existing) {
                $this->ensure((int) $existing->quantity === $quantity, 'Reference was used with a different quantity.');

                return $existing;
            }
            $this->ensure($variant->status === 'active' && $variant->product()->where('status', 'active')->exists(), 'Product is not available for purchase.');
            $this->ensure($variant->available_stock >= $quantity, 'Insufficient available stock.');
            $id = DB::table('inventory_reservations')->insertGetId([
                'product_variant_id' => $variantId, 'reference' => $reference, 'quantity' => $quantity,
                'status' => 'pending', 'expires_at' => now()->addMinutes($ttlMinutes), 'created_at' => now(), 'updated_at' => now(),
            ]);
            $this->record($variant, 'reserve', $quantity, 0, $quantity, 'Checkout reservation', 'reserve:'.$reference, $reference);

            return DB::table('inventory_reservations')->find($id);
        }, 5);
    }

    public function commit(int $variantId, string $reference): object
    {
        return $this->transition($variantId, $reference, 'committed');
    }

    public function release(int $variantId, string $reference): object
    {
        return $this->transition($variantId, $reference, 'released');
    }

    public function expire(int $variantId, string $reference): object
    {
        return $this->transition($variantId, $reference, 'expired');
    }

    public function returnStock(int $variantId, string $reference, int $quantity, string $key, string $reason, ?int $actorId = null): object
    {
        $this->ensure($quantity > 0 && $quantity <= 1000000000, 'Return quantity must be positive.');

        return DB::transaction(function () use ($variantId, $reference, $quantity, $key, $reason, $actorId): object {
            $variant = $this->lockedVariant($variantId);
            $reservation = DB::table('inventory_reservations')->where('product_variant_id', $variantId)->where('reference', $reference)->lockForUpdate()->first();
            $this->ensure($reservation && $reservation->status === 'committed', 'Only a committed sale can be returned.');
            $existing = DB::table('stock_movements')->where('product_variant_id', $variantId)->where('idempotency_key', 'return:'.$key)->first();
            if ($existing) {
                $this->ensure($existing->reference === $reference && (int) $existing->quantity === $quantity && $existing->reason === $reason && (int) $existing->actor_id === (int) $actorId, 'Idempotency key was used with a different request.');

                return $existing;
            }
            $this->ensure($reservation->returned_quantity + $quantity <= $reservation->quantity, 'Return exceeds sold quantity.');
            $this->ensure($variant->stock_quantity + $quantity <= 2147483647 && $variant->sold_quantity >= $quantity, 'Invalid stock balance.');
            DB::table('inventory_reservations')->where('id', $reservation->id)->update(['returned_quantity' => $reservation->returned_quantity + $quantity, 'updated_at' => now()]);
            $variant->sold_quantity -= $quantity;

            return $this->record($variant, 'return', $quantity, $quantity, 0, $reason, 'return:'.$key, $reference, $actorId);
        }, 5);
    }

    private function transition(int $variantId, string $reference, string $target): object
    {
        return DB::transaction(function () use ($variantId, $reference, $target): object {
            $variant = $this->lockedVariant($variantId);
            $reservation = DB::table('inventory_reservations')->where('product_variant_id', $variantId)->where('reference', $reference)->lockForUpdate()->first();
            $this->ensure((bool) $reservation, 'Reservation does not exist.');
            if ($reservation->status === $target || ($target === 'expired' && $reservation->status !== 'pending')) {
                return $reservation;
            }
            $this->ensure($reservation->status === 'pending', 'Reservation is already closed.');
            $expired = now()->greaterThanOrEqualTo($reservation->expires_at);
            $this->ensure($target !== 'committed' || ! $expired, 'Reservation expired; payment needs reconciliation.');
            $this->ensure($target !== 'expired' || $expired, 'Reservation has not expired.');
            $quantity = (int) $reservation->quantity;
            $this->ensure($variant->reserved_quantity >= $quantity, 'Invalid reservation balance.');
            if ($target === 'committed') {
                $this->ensure($variant->sold_quantity + $quantity <= 2147483647, 'Sold quantity exceeds supported limit.');
                $variant->sold_quantity += $quantity;
            }
            $this->record($variant, $target === 'committed' ? 'sale' : $target, $quantity, $target === 'committed' ? -$quantity : 0, -$quantity, 'Reservation '.$target, $target.':'.$reference, $reference);
            DB::table('inventory_reservations')->where('id', $reservation->id)->update(['status' => $target, 'updated_at' => now()]);

            return DB::table('inventory_reservations')->find($reservation->id);
        }, 5);
    }

    private function record(ProductVariant $variant, string $type, int $quantity, int $stockDelta, int $reservedDelta, string $reason, string $key, ?string $reference = null, ?int $actorId = null): object
    {
        $before = (int) $variant->stock_quantity;
        $reserved = (int) $variant->reserved_quantity;
        $variant->stock_quantity += $stockDelta;
        $variant->reserved_quantity += $reservedDelta;
        $variant->save();
        $available = ProductVariant::query()->where('product_id', $variant->product_id)->where('status', 'active')->whereColumn('stock_quantity', '>', 'reserved_quantity')->exists();
        Product::withTrashed()->whereKey($variant->product_id)->where('stock_status', '!=', 'preorder')->update(['stock_status' => $available ? 'in_stock' : 'out_of_stock']);
        $id = DB::table('stock_movements')->insertGetId([
            'product_variant_id' => $variant->id, 'actor_id' => $actorId, 'type' => $type,
            'quantity' => $quantity, 'stock_before' => $before, 'stock_after' => $variant->stock_quantity,
            'reserved_before' => $reserved, 'reserved_after' => $variant->reserved_quantity,
            'reason' => $reason, 'idempotency_key' => $key, 'reference' => $reference, 'created_at' => now(),
        ]);

        return DB::table('stock_movements')->find($id);
    }

    private function ensure(bool $condition, string $message): void
    {
        if (! $condition) {
            throw ValidationException::withMessages(['inventory' => [$message]]);
        }
    }

    /** Parent-first locking matches catalog updates and serializes writers for one product. */
    private function lockedVariant(int $variantId): ProductVariant
    {
        $variant = ProductVariant::withTrashed()->findOrFail($variantId);
        Product::withTrashed()->whereKey($variant->product_id)->lockForUpdate()->firstOrFail();

        return ProductVariant::withTrashed()->whereKey($variantId)->lockForUpdate()->firstOrFail();
    }
}
