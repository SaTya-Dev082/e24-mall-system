<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class StorefrontCart
{
    /** @return array{quantities: array, unselected: array} */
    public function state(Request $request): array
    {
        $user = $request->user('web');
        if (! $user) {
            return ['quantities' => $request->session()->get('storefront_cart', []), 'unselected' => $request->session()->get('storefront_cart_unselected', [])];
        }
        $cartId = DB::table('carts')->where('user_id', $user->id)->value('id');
        $lines = $cartId ? DB::table('cart_items')->where('cart_id', $cartId)->whereNotNull('product_variant_id')->get() : collect();

        return ['quantities' => $lines->mapWithKeys(fn ($line) => [(int) $line->product_variant_id => (int) $line->quantity])->all(), 'unselected' => $lines->where('is_selected', false)->pluck('product_variant_id')->map(fn ($id) => (int) $id)->all()];
    }

    public function count(Request $request): int
    {
        return array_sum($this->state($request)['quantities']);
    }

    /** Serialize account cart mutations by locking the owner; guest routes use session blocking. */
    private function mutate(Request $request, Closure $operation): void
    {
        $user = $request->user('web');
        if (! $user) {
            $state = $operation($this->state($request));
            if (! $request->session()->has('storefront_cart_merge_key')) {
                $request->session()->put('storefront_cart_merge_key', Str::random(64));
            }
            $request->session()->put('storefront_cart', $state['quantities']);
            $request->session()->put('storefront_cart_unselected', $state['unselected']);

            return;
        }
        DB::transaction(function () use ($user, $request, $operation): void {
            $owner = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            abort_unless($owner->status === 'active' && $owner->role === 'customer' && $owner->deleted_at === null, 403);
            $state = $operation($this->state($request));
            $cart = DB::table('carts')->where('user_id', $user->id)->first();
            $cartId = $cart?->id ?? DB::table('carts')->insertGetId(['user_id' => $user->id, 'created_at' => now(), 'updated_at' => now()]);
            DB::table('cart_items')->where('cart_id', $cartId)->whereNotNull('product_variant_id')->whereNotIn('product_variant_id', array_keys($state['quantities']))->delete();
            $variants = ProductVariant::whereIn('id', array_keys($state['quantities']))->get()->keyBy('id');
            foreach ($state['quantities'] as $id => $quantity) {
                $variant = $variants->get($id);
                if (! $variant) {
                    continue;
                }
                $existing = DB::table('cart_items')->where('cart_id', $cartId)->where('product_variant_id', $id)->first();
                $data = ['quantity' => $quantity, 'price' => $variant->price, 'is_selected' => ! in_array((int) $id, $state['unselected'], true), 'updated_at' => now()];
                if ($existing) {
                    DB::table('cart_items')->where('id', $existing->id)->update($data);
                } else {
                    DB::table('cart_items')->insert($data + ['cart_id' => $cartId, 'product_id' => $variant->product_id, 'product_variant_id' => $id, 'created_at' => now()]);
                }
            }
            DB::table('carts')->where('id', $cartId)->update(['updated_at' => now()]);
        }, 5);
    }

    private function validateOption(Product $product, ProductVariant $variant, int $quantity): void
    {
        if ($product->trashed() || $product->status !== 'active' || $variant->product_id !== $product->id || $variant->status !== 'active' || $variant->trashed() || $quantity < 1 || $quantity > min(99, max(0, $variant->available_stock))) {
            throw ValidationException::withMessages(['cart' => 'This option is unavailable or the requested quantity exceeds available stock (maximum 99 per option).']);
        }
    }

    public function add(Request $request, Product $product, ProductVariant $variant, int $quantity): void
    {
        $this->mutate($request, function (array $state) use ($product, $variant, $quantity): array {
            $next = (int) ($state['quantities'][$variant->id] ?? 0) + $quantity;
            $this->validateOption($product, $variant, $next);
            $state['quantities'][$variant->id] = $next;
            $state['unselected'] = array_values(array_diff($state['unselected'], [$variant->id]));

            return $state;
        });
    }

    public function buyNow(Request $request, Product $product, ProductVariant $variant, int $quantity): void
    {
        $this->mutate($request, function (array $state) use ($product, $variant, $quantity): array {
            $this->validateOption($product, $variant, $quantity);
            $state['quantities'][$variant->id] = $quantity;
            $state['unselected'] = array_values(array_diff(array_map('intval', array_keys($state['quantities'])), [$variant->id]));

            return $state;
        });
    }

    public function update(Request $request, int $variantId, int $quantity): void
    {
        $this->mutate($request, function (array $state) use ($variantId, $quantity): array {
            abort_unless(array_key_exists($variantId, $state['quantities']), 404);
            $variant = ProductVariant::with('product')->find($variantId);
            if (! $variant?->product) {
                throw ValidationException::withMessages(['cart' => 'This option is no longer available.']);
            }
            $this->validateOption($variant->product, $variant, $quantity);
            $state['quantities'][$variantId] = $quantity;

            return $state;
        });
    }

    public function select(Request $request, array $selected): void
    {
        $this->mutate($request, function (array $state) use ($selected): array {
            $owned = array_map('intval', array_keys($state['quantities']));
            abort_if(array_diff($selected, $owned) !== [], 404);
            $state['unselected'] = array_values(array_diff($owned, $selected));

            return $state;
        });
    }

    public function remove(Request $request, array $ids): void
    {
        $this->mutate($request, function (array $state) use ($ids): array {
            abort_if(array_diff($ids, array_keys($state['quantities'])) !== [], 404);
            foreach ($ids as $id) {
                unset($state['quantities'][$id]);
            }
            $state['unselected'] = array_values(array_diff($state['unselected'], $ids));

            return $state;
        });
    }

    /** Guest lines are merged once, capped to available stock; existing account lines are retained. */
    public function mergeGuest(Request $request): void
    {
        $guest = $request->session()->get('storefront_cart', []);
        $unselected = $request->session()->get('storefront_cart_unselected', []);
        $changed = false;
        $mergeKey = $request->session()->get('storefront_cart_merge_key', hash('sha256', $request->session()->getId()));
        $userId = $request->user('web')->id;
        if ($guest !== []) {
            $this->mutate($request, function (array $state) use ($guest, $unselected, $mergeKey, $userId, &$changed): array {
                if (DB::table('cart_merge_receipts')->where('user_id', $userId)->where('merge_key', $mergeKey)->exists()) {
                    return $state;
                }
                $variants = ProductVariant::with('product')->whereIn('id', array_keys($guest))->get()->keyBy('id');
                foreach ($guest as $id => $quantity) {
                    $variant = $variants->get($id);
                    if (! $variant?->product || $variant->status !== 'active' || $variant->product->status !== 'active' || $variant->available_stock < 1) {
                        $changed = true;

                        continue;
                    }
                    $existing = (int) ($state['quantities'][$id] ?? 0);
                    $next = min(99, $variant->available_stock, $existing + (int) $quantity);
                    $changed = $changed || $next !== $existing + (int) $quantity;
                    $state['quantities'][$id] = max($existing, $next);
                    if ($existing === 0 && in_array((int) $id, $unselected, true)) {
                        $state['unselected'][] = (int) $id;
                    }
                }

                DB::table('cart_merge_receipts')->insert(['user_id' => $userId, 'merge_key' => $mergeKey, 'created_at' => now()]);

                return $state;
            });
        }
        $request->session()->forget(['storefront_cart', 'storefront_cart_unselected', 'storefront_cart_merge_key']);
        if ($changed) {
            $request->session()->flash('cart_notice', 'Your carts were merged. Some guest quantities were adjusted or unavailable items skipped; please review your cart.');
        }
    }

    /** @return array{items: array, subtotal: string, count: int, selected_count: int, can_checkout: bool} */
    public function contents(Request $request): array
    {
        $state = $this->state($request);
        $quantities = $state['quantities'];
        $variants = ProductVariant::with('product.primaryImage')->whereIn('id', array_keys($quantities))->get()->keyBy('id');
        $items = [];
        $subtotal = 0;
        $selectedCount = 0;
        $canCheckout = true;
        foreach ($quantities as $id => $quantity) {
            $variant = $variants->get($id);
            $product = $variant?->product;
            $available = $variant && $product && $product->status === 'active' && $variant->status === 'active' && $variant->available_stock >= $quantity;
            $price = $variant?->price ?? '0.00';
            [$whole, $fraction] = array_pad(explode('.', $price, 2), 2, '00');
            $line = ((int) $whole * 100 + (int) str_pad($fraction, 2, '0')) * $quantity;
            $selected = ! in_array((int) $id, $state['unselected'], true);
            if ($selected) {
                $selectedCount += $quantity;
                $canCheckout = $canCheckout && $available;
            }
            if ($available && $selected) {
                $subtotal += $line;
            }
            $items[] = ['id' => $id, 'product' => $product, 'variant' => $variant, 'quantity' => $quantity, 'available' => $available, 'selected' => $selected, 'line_total' => $this->decimal($line)];
        }

        return ['items' => $items, 'subtotal' => $this->decimal($subtotal), 'count' => array_sum($quantities), 'selected_count' => $selectedCount, 'can_checkout' => $selectedCount > 0 && $canCheckout];
    }

    private function decimal(int $cents): string
    {
        return intdiv($cents, 100).'.'.str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }
}
