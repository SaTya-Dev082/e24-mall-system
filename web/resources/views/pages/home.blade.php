@extends('layouts.storefront')
@section('content')
<div class="nova-home">
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

<section class="section"><div class="section-heading"><h2>Latest products</h2><a href="{{ route('storefront.products.index', ['sort' => 'newest']) }}">View all →</a></div><div class="product-grid">@forelse($latest as $product)<x-product-card :product="$product" />@empty<div class="empty"><h2>New discoveries are on their way</h2><p>Published products will appear here.</p></div>@endforelse</div></section>

</div></div>
@if($featured->isNotEmpty())<section class="section featured-section"><div class="section-heading"><h2>Featured products</h2><a href="{{ route('storefront.products.index') }}">Explore catalog →</a></div><div class="product-grid">@foreach($featured as $product)<x-product-card :product="$product" />@endforeach</div></section>@endif

@endsection
