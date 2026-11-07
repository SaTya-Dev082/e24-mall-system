<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Services\StorefrontCart;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CartController extends Controller
{
    public function index(Request $request, StorefrontCart $cart): View
    {
        return view('pages.cart', $cart->contents($request));
    }

    public function store(Request $request, string $slug, StorefrontCart $cart): RedirectResponse
    {
        $data = $request->validate(['variant' => ['nullable', 'integer', 'min:1'], 'quantity' => ['sometimes', 'integer', 'min:1', 'max:99'], 'intent' => ['sometimes', 'in:buy_now']]);
        $product = Product::where('status', 'active')->where('slug', $slug)->firstOrFail();
        if (isset($data['variant'])) {
            $variant = $product->variants()->whereKey($data['variant'])->firstOrFail();
        } else {
            $options = $product->variants()->where('status', 'active')->whereColumn('stock_quantity', '>', 'reserved_quantity')->limit(2)->get();
            if ($options->count() !== 1) {
                return redirect()->route('storefront.products.show', $slug)->with('cart_notice', 'Choose an available option before adding this product to your cart.');
            }
            $variant = $options->first();
        }
        $cart->add($request, $product, $variant, (int) ($data['quantity'] ?? 1));

        if (($data['intent'] ?? null) === 'buy_now') {
            return redirect()->route('storefront.cart.index')->with('cart_notice', 'Added to cart. Review your selection below.');
        }

        return back()->with('cart_notice', 'Added to cart.');
    }

    public function remove(Request $request, int $variant): RedirectResponse
    {
        $cart = $request->session()->get('storefront_cart', []);
        unset($cart[$variant]);
        $request->session()->put('storefront_cart', $cart);

        return back()->with('cart_notice', 'Item removed.');
    }
}
