@extends('layouts.storefront')
@section('content')
<div class="nova-home">
<aside class="nova-sidebar"><div class="sidebar-edit"><p class="eyebrow">THE E24 EDIT</p><h2>Little upgrades.<br>Big possibilities.</h2><p>Discover a new favorite for your everyday.</p><a class="button" href="{{ route('storefront.products.index') }}">Explore now →</a><div class="edit-monogram" aria-hidden="true">E24</div></div>
@if($featured->isNotEmpty())<div class="sidebar-product"><p class="eyebrow">IN THE SPOTLIGHT</p><h2>A closer look</h2><a href="{{ route('storefront.products.show', $featured->first()->slug) }}"><img src="{{ $featured->first()->primaryImage?->image_url ?: asset('images/product-placeholder.svg') }}" alt="{{ $featured->first()->name }}" data-fallback><h3>{{ $featured->first()->name }}</h3><strong>{{ \App\Support\StorefrontFormat::money($featured->first()->price) }}</strong><span>View details →</span></a></div>@endif
</aside>
<div class="nova-content">
@if($banners->isNotEmpty())
<section class="storefront-banners" aria-label="Store announcements" aria-roledescription="carousel" data-banner-carousel>
    @foreach($banners as $banner)
        <div @class(['banner-slide', 'banner-with-copy' => $banner->headline || $banner->description]) role="group" aria-roledescription="slide" aria-label="{{ $loop->iteration }} of {{ $banners->count() }}" data-banner-slide @if(!$loop->first) hidden @endif>
            @if($banner->headline || $banner->description)
                <div class="banner-copy">
                    <p class="eyebrow">THE E24 COLLECTION</p>
                    @if($banner->headline)<h1>{{ $banner->headline }}</h1>@endif
                    @if($banner->description)<p class="banner-description">{{ $banner->description }}</p>@endif
                    @if($banner->link_url && $banner->button_text)<a class="button" href="{{ $banner->link_url }}">{{ $banner->button_text }} <span aria-hidden="true">→</span></a>@endif
                </div>
            @endif
            <div class="banner-artwork">
                @if($banner->link_url)<a href="{{ $banner->link_url }}">@endif
                <img src="{{ $banner->image_url }}" alt="{{ $banner->alt_text }}" width="1600" height="600" @if($loop->first) fetchpriority="high" @else loading="lazy" @endif>
                @if($banner->link_url)</a>@endif
            </div>
        </div>
    @endforeach
    @if($banners->count() > 1)<div class="banner-controls banner-dots-only"><button type="button" class="sr-only" data-banner-prev aria-label="Previous banner">←</button><div class="banner-dots">@foreach($banners as $banner)<button type="button" data-banner-dot aria-label="Show banner {{ $loop->iteration }}" aria-pressed="{{ $loop->first ? 'true' : 'false' }}"></button>@endforeach</div><span class="sr-only" data-banner-position>1 / {{ $banners->count() }}</span><button type="button" class="sr-only" data-banner-next aria-label="Next banner">→</button><button type="button" class="banner-toggle sr-only" data-banner-toggle aria-label="Pause automatic banner rotation">Pause</button></div>@endif
</section>
@endif

<div class="catalog-benefits"><div><span aria-hidden="true">▦</span><p><strong>All in one place</strong><small>Explore our categories</small></p></div><div><span aria-hidden="true">↻</span><p><strong>Fresh discoveries</strong><small>Browse the latest arrivals</small></p></div><div><span aria-hidden="true">✓</span><p><strong>Clear availability</strong><small>Stock shown by option</small></p></div><div><span aria-hidden="true">⌕</span><p><strong>Find your fit</strong><small>Search, filter and explore</small></p></div></div>
<section class="section"><div class="section-heading"><h2>Latest products</h2><a href="{{ route('storefront.products.index', ['sort' => 'newest']) }}">View all →</a></div><div class="product-grid">@forelse($latest as $product)<x-product-card :product="$product" />@empty<div class="empty"><h2>New discoveries are on their way</h2><p>Published products will appear here.</p></div>@endforelse</div></section>
<div class="editorial-grid"><a class="editorial-tile" href="{{ route('storefront.products.index', ['availability' => 'in_stock']) }}"><div><p class="eyebrow">READY TO EXPLORE</p><h2>Find your next<br>everyday essential.</h2><span class="mini-button">Browse in-stock finds →</span></div><span class="editorial-symbol" aria-hidden="true">✳</span></a><a class="editorial-tile warm" href="{{ route('storefront.products.index', ['sort' => 'newest']) }}"><div><p class="eyebrow">A FRESH PERSPECTIVE</p><h2>Something new.<br>Something for you.</h2><span class="mini-button">Explore new arrivals →</span></div><span class="editorial-symbol" aria-hidden="true">↗</span></a></div>
<section class="section"><div class="section-heading"><h2>Shop by category</h2><a href="{{ route('storefront.products.index') }}">View all →</a></div><div class="nova-categories">@foreach($categories as $category)<a href="{{ route('storefront.products.index', ['category' => $category->slug]) }}"><span aria-hidden="true">{{ str_pad($loop->iteration, 2, '0', STR_PAD_LEFT) }}</span><strong>{{ $category->name }}</strong></a>@endforeach</div></section>
</div></div>
@if($featured->isNotEmpty())<section class="section featured-section"><div class="section-heading"><h2>Featured products</h2><a href="{{ route('storefront.products.index') }}">Explore catalog →</a></div><div class="product-grid">@foreach($featured as $product)<x-product-card :product="$product" />@endforeach</div></section>@endif
<section class="nova-bottom"><div class="bottom-icon" aria-hidden="true">✉</div><div><h2>Your next discovery starts here.</h2><p>Explore the E24 collection and find something that feels like you.</p></div><a class="button" href="{{ route('storefront.products.index') }}">Explore E24 →</a></section>
@endsection
