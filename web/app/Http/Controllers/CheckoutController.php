<?php

namespace App\Http\Controllers;

use App\Services\StorefrontCart;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class CheckoutController extends Controller
{
    public function index(Request $request, StorefrontCart $cart): View|RedirectResponse
    {
        $contents = $cart->contents($request);
        if (! $contents['can_checkout']) {
            return redirect()->route('storefront.cart.index')->withErrors(['cart' => 'Select available items and check their quantities before checkout.']);
        }

        return view('pages.checkout', $contents + ['addresses' => DB::table('addresses')->where('user_id', $request->user('web')->id)->orderByDesc('is_default')->orderByDesc('id')->get()]);
    }
}
