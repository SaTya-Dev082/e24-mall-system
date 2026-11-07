@extends('layouts.storefront')
@section('title', 'Your cart — E24')
@section('content')
<div class="page-heading"><p class="eyebrow">YOUR SELECTION</p><h1>Your cart</h1><p>Prices and availability are checked against the current catalog. Items are not reserved.</p></div>
@forelse($items as $item)
<article class="cart-row"><div><h2>@if($item['product'])<a href="{{ route('storefront.products.show', $item['product']->slug) }}">{{ $item['product']->name }}</a>@else Unavailable product @endif</h2><p>{{ $item['variant']?->name ?? 'Unavailable option' }} · Quantity: {{ $item['quantity'] }}</p><p>{{ $item['available'] ? 'In stock' : 'Unavailable or insufficient stock — remove this item before ordering.' }}</p></div><strong>{{ \App\Support\StorefrontFormat::money($item['line_total']) }}</strong><form method="POST" action="{{ route('storefront.cart.remove', $item['id']) }}">@csrf @method('DELETE')<button class="button">Remove</button></form></article>
@empty<div class="empty"><h2>Your cart is empty</h2><a href="{{ route('storefront.products.index') }}">Browse products →</a></div>@endforelse
@if(count($items))<div class="cart-summary"><h2>Available items subtotal: {{ \App\Support\StorefrontFormat::money($subtotal) }}</h2><p>Shipping and taxes are not calculated. Checkout is coming in a later phase.</p></div>@endif
@endsection
