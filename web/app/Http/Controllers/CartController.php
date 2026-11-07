<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Services\StorefrontCart;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CartController extends Controller
{
    public function index(Request $request, StorefrontCart $cart): View|JsonResponse
    {
        if ($request->expectsJson()) {
            return $this->cartResponse($request, $cart);
        }

        return view('pages.cart', $cart->contents($request));
    }

    public function store(Request $request, string $slug, StorefrontCart $cart): RedirectResponse|JsonResponse
    {
        $data = $request->validate(['variant' => ['nullable', 'integer', 'min:1'], 'quantity' => ['sometimes', 'integer', 'min:1', 'max:99'], 'intent' => ['sometimes', 'in:buy_now']]);
        $product = Product::where('status', 'active')->where('slug', $slug)->firstOrFail();
        if (isset($data['variant'])) {
            $variant = $product->variants()->whereKey($data['variant'])->firstOrFail();
        } else {
            $options = $product->variants()->where('status', 'active')->whereColumn('stock_quantity', '>', 'reserved_quantity')->orderBy('id')->get(['id', 'product_id', 'name', 'price', 'stock_quantity', 'reserved_quantity', 'status']);
            if ($options->count() !== 1) {
                if ($request->expectsJson()) {
                    if ($options->isEmpty()) {
                        return response()->json(['message' => 'This product is currently out of stock.'], 422);
                    }

                    return response()->json(['message' => 'Choose an option', 'options' => $options->map(fn ($option) => ['id' => $option->id, 'name' => $option->name, 'price' => $option->price, 'stock' => max(0, $option->available_stock)])], 409);
                }

                return redirect()->route('storefront.products.show', $slug)->with('cart_notice', 'Choose an available option before adding this product to your cart.');
            }
            $variant = $options->first();
        }
        if (($data['intent'] ?? null) === 'buy_now') {
            $cart->buyNow($request, $product, $variant, (int) ($data['quantity'] ?? 1));
        } else {
            $cart->add($request, $product, $variant, (int) ($data['quantity'] ?? 1));
        }

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Added to cart.', 'count' => $cart->count($request)]);
        }

        if (($data['intent'] ?? null) === 'buy_now') {
            return redirect()->route('checkout.index');
        }

        return back()->with('cart_notice', 'Added to cart.');
    }

    public function update(Request $request, int $variant, StorefrontCart $cart): RedirectResponse|JsonResponse
    {
        $data = $request->validate(['quantity' => ['required', 'integer', 'min:1', 'max:99']]);
        $cart->update($request, $variant, (int) $data['quantity']);

        return $this->cartResponse($request, $cart);
    }

    public function selection(Request $request, StorefrontCart $cart): RedirectResponse|JsonResponse
    {
        $cart->select($request, $this->selectedIds($request));

        return $this->cartResponse($request, $cart);
    }

    public function removeSelected(Request $request, StorefrontCart $cart): RedirectResponse|JsonResponse
    {
        $cart->remove($request, $this->selectedIds($request));

        return $this->cartResponse($request, $cart);
    }

    /** @return array<int> */
    private function selectedIds(Request $request): array
    {
        $data = $request->validate(['selected' => ['sometimes', 'array', 'max:500'], 'selected.*' => ['required', 'integer', 'min:1', 'distinct']]);

        return array_map('intval', $data['selected'] ?? []);
    }

    public function remove(Request $request, int $variant, StorefrontCart $cart): RedirectResponse|JsonResponse
    {
        $cart->remove($request, [$variant]);

        return $this->cartResponse($request, $cart);
    }

    private function cartResponse(Request $request, StorefrontCart $cart): RedirectResponse|JsonResponse
    {
        if ($request->expectsJson()) {
            $contents = $cart->contents($request);

            return response()->json(['html' => view('pages.cart-content', $contents)->render(), 'count' => $contents['count']]);
        }

        return redirect()->route('storefront.cart.index');
    }
}
