<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class StorefrontCart
{
    /** Add quantities only; prices and availability always come from the catalog. */
    public function add(Request $request, Product $product, ProductVariant $variant, int $quantity): void
    {
        $cart = $request->session()->get('storefront_cart', []);
        $next = (int) ($cart[$variant->id] ?? 0) + $quantity;
        if ($product->status !== 'active' || $variant->product_id !== $product->id || $variant->status !== 'active' || $variant->trashed() || $next > min(99, max(0, $variant->available_stock))) {
            throw ValidationException::withMessages(['cart' => 'This option is unavailable or the requested quantity exceeds available stock (maximum 99 per option).']);
        }
        $cart[$variant->id] = $next;
        $request->session()->put('storefront_cart', $cart);
    }

    /** @return array{items: array, subtotal: string} */
    public function contents(Request $request): array
    {
        $quantities = $request->session()->get('storefront_cart', []);
        $variants = ProductVariant::query()->with('product')->whereIn('id', array_keys($quantities))->get()->keyBy('id');
        $items = [];
        $subtotal = 0;
        foreach ($quantities as $id => $quantity) {
            $variant = $variants->get($id);
            $product = $variant?->product;
            $available = $variant && $product && $product->status === 'active' && $variant->status === 'active' && $variant->available_stock >= $quantity;
            $price = $variant?->price ?? '0.00';
            [$whole, $fraction] = array_pad(explode('.', $price, 2), 2, '00');
            $line = ((int) $whole * 100 + (int) str_pad($fraction, 2, '0')) * $quantity;
            if ($available) {
                $subtotal += $line;
            }
            $items[] = ['id' => $id, 'product' => $product, 'variant' => $variant, 'quantity' => $quantity, 'available' => $available, 'line_total' => $this->decimal($line)];
        }

        return ['items' => $items, 'subtotal' => $this->decimal($subtotal)];
    }

    private function decimal(int $cents): string
    {
        return intdiv($cents, 100).'.'.str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }
}
