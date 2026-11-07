@props(['product'])
<article class="product-card product-card-compact">
    <a class="compact-item-link" href="{{ route('storefront.products.show', $product->slug) }}" aria-label="View details for {{ $product->name }}">
        <div class="compact-image"><img loading="lazy" width="400" height="400" src="{{ $product->primaryImage?->image_url ?: asset('images/product-placeholder.svg') }}" alt="" data-fallback></div>
        <div class="compact-badges">
            @if($product->created_at?->between(now()->subDays(30), now()))<span class="compact-new">New</span>@endif
            <span class="compact-stock {{ $product->has_available_stock ? 'available' : 'unavailable' }}">{{ $product->has_available_stock ? 'In Stock' : 'Out of Stock' }}</span>
        </div>
        <div class="compact-title-row"><h3 title="{{ $product->name }}">{{ $product->name }}</h3></div>
        <div class="compact-description"><p data-card-description><span data-description-text>{{ $product->short_description ?: 'Explore product details.' }}</span><span class="compact-description-more" data-description-more hidden>…more</span></p></div>

    </a>
    @if(filled($product->location))
        <span class="compact-location" title="{{ $product->location }}">{{ $product->location }}</span>
    @endif
    <div class="compact-meta">
        <strong class="compact-price">{{ \App\Support\StorefrontFormat::money($product->price) }}</strong>
        @if($product->created_at)
            <time datetime="{{ $product->created_at->toIso8601String() }}" title="{{ \App\Support\StorefrontFormat::date($product->created_at) }}">{{ \App\Support\StorefrontFormat::productAge($product->created_at) }}</time>
        @endif
    </div>
    <form class="compact-cart-form" method="POST" action="{{ route('storefront.cart.store', $product->slug) }}">
        @csrf
        <button class="compact-button" type="submit" @disabled(!$product->has_available_stock) aria-label="Add {{ $product->name }} to cart">Add to Cart</button>
    </form>
</article>
