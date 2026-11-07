@extends('layouts.storefront')
@section('body-class', 'product-detail-page')
@section('navigation-category', $product->category?->slug ?? '')
@section('title', $product->name.' — E24')
@section('content')
<div class="breadcrumbs"><a href="{{ route('home') }}">Home</a><span>›</span><a href="{{ route('storefront.products.index') }}">Products</a><span>›</span><span>{{ $product->name }}</span></div>
<article class="detail-shell detail-minimal">
<section class="product-detail">
<div class="detail-gallery"><div class="detail-image"><img id="gallery-main" width="800" height="800" src="{{ $product->images->first()?->image_url ?: asset('images/product-placeholder.svg') }}" alt="{{ $product->images->first()?->alt_text ?: $product->name }}" data-fallback></div>
<div class="thumbnails" role="group" aria-label="Product images">@foreach($product->images as $image)<button type="button" data-gallery="{{ $image->image_url }}" data-alt="{{ $image->alt_text ?: $product->name }}" aria-pressed="{{ $loop->first ? 'true' : 'false' }}" aria-label="View image {{ $loop->iteration }}"><span class="thumbnail-image"><img src="{{ $image->image_url }}" alt="" loading="lazy" data-fallback></span><span class="thumbnail-caption">{{ $loop->first ? 'Main view' : 'View '.$loop->iteration }}</span></button>@endforeach</div>
</div>
<div class="detail-copy">@if($product->created_at?->between(now()->subDays(30), now()))<span class="detail-new">New</span>@endif<div class="detail-kicker"><span>{{ $product->brand?->name ?: 'E24 COLLECTION' }}</span>@if($product->category)<a href="{{ route('storefront.products.index', ['category' => $product->category->slug]) }}">{{ $product->category->name }} ↗</a>@endif</div><h1>{{ $product->name }}</h1><p class="detail-subtitle">{{ $product->short_description ?: 'Explore the details and available options below.' }}</p>
<div class="detail-price-block"><span class="price-label">Product price</span><p class="detail-price" id="selected-price" aria-live="polite">{{ \App\Support\StorefrontFormat::money($product->price) }}</p></div>
@if($product->variants->isNotEmpty())<fieldset class="variant-options"><legend>Choose an option</legend>@foreach($product->variants as $variant)<label class="variant {{ $variant->status !== 'active' || $variant->available_stock <= 0 ? 'unavailable' : '' }}"><input type="radio" name="variant" form="detail-cart-form" required value="{{ $variant->id }}" data-price="{{ ltrim(\App\Support\StorefrontFormat::money($variant->price), '$') }}" data-stock="{{ max(0, $variant->available_stock) }}" @disabled($variant->status !== 'active' || $variant->available_stock <= 0)><span><strong>{{ $variant->name }}</strong><small>@foreach($variant->values as $selection){{ $selection->attribute?->name }}: {{ $selection->value?->value }}{{ $loop->last ? '' : ' · ' }}@endforeach</small><small>{{ $variant->status !== 'active' ? 'Unavailable' : ($variant->available_stock > 0 ? max(0, $variant->available_stock).' available' : 'Out of stock') }} · {{ \App\Support\StorefrontFormat::money($variant->price) }}</small></span></label>@endforeach</fieldset><p id="selected-stock" role="status">Select an option to see its price and available stock.</p><noscript><p>Each option’s price and stock are listed above. Enable JavaScript for live selection updates.</p></noscript>@else<p class="stock">{{ $product->stock_status === 'preorder' ? 'Preorder · availability to be confirmed' : 'Currently unavailable' }}</p>@endif

@if($product->variants->where('status', 'active')->contains(fn ($variant) => $variant->available_stock > 0))
<form class="detail-cart-form" method="POST" action="{{ route('storefront.cart.store', $product->slug) }}" id="detail-cart-form">
    @csrf
    <label class="quantity-label" for="detail-quantity">Quantity</label>
    <div class="detail-quantity"><button type="button" data-quantity-step="-1" aria-label="Decrease quantity">−</button><input id="detail-quantity" type="number" name="quantity" min="1" max="99" value="1" required inputmode="numeric"><button type="button" data-quantity-step="1" aria-label="Increase quantity">+</button></div>
    <div class="detail-purchase-actions">
        <button class="button detail-buy" type="submit" name="intent" value="buy_now">Buy Now</button>
        <button class="button detail-buy" type="submit">Add to Cart</button>
    </div>
    <p>Choose your option, then review it in your cart. Checkout is not yet available.</p>
</form>
@endif
</div></section>
</article>
@if($related->isNotEmpty())<section class="section detail-related"><div class="section-heading"><h2>You might also like</h2><a href="{{ route('storefront.products.index') }}">Explore all →</a></div><div class="product-grid">@foreach($related->take(2) as $relatedProduct)<x-product-card :product="$relatedProduct" />@endforeach</div></section>@endif
@endsection
