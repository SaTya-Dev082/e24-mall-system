<!DOCTYPE html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>@yield('title', 'E24 — Everyday discoveries')</title>@vite(['resources/css/app.css', 'resources/js/app.js'])<noscript><style>@media(max-width:600px){.nav{display:flex}.mobile-menu{display:none}}</style></noscript></head>
<body class="@yield('body-class')">
<a class="skip-link" href="#main-content">Skip to content</a>
<header class="header wrap"><a class="logo" href="{{ route('home') }}" aria-label="E24 home">E24<span>®</span></a><form class="search" action="{{ route('storefront.products.index') }}" role="search"><label class="sr-only" for="search">Search products</label><input id="search" name="search" maxlength="150" placeholder="Search for products, brands and more…" value="{{ is_string(request('search')) ? request('search') : '' }}"><button>Find ↗</button></form><a class="header-cart" href="{{ route('storefront.cart.index') }}">Cart ({{ array_sum(session('storefront_cart', [])) }})</a></header>
<button class="mobile-menu wrap" type="button" aria-controls="category-navigation" aria-expanded="false">Browse categories ☰</button>
@php
    $navigationCategory = request()->routeIs('storefront.products.index')
        ? request('category')
        : trim($__env->yieldContent('navigation-category'));
    $homeActive = request()->routeIs('home');
    $catalogActive = request()->routeIs('storefront.products.*') && !$navigationCategory;
@endphp
<nav id="category-navigation" class="nav wrap" aria-label="Categories">
    <a href="{{ route('home') }}" @if($homeActive) aria-current="page" @endif>Home</a>
    <a href="{{ route('storefront.products.index') }}" @if($catalogActive) aria-current="page" @endif>All products</a>
    @foreach(($categories ?? []) as $category)
        <a href="{{ route('storefront.products.index', ['category' => $category->slug]) }}" @if(!$homeActive && $navigationCategory === $category->slug) aria-current="page" @endif>{{ $category->name }}</a>
    @endforeach
</nav>
<main id="main-content" class="wrap" tabindex="-1">@if(session('cart_notice'))<div class="cart-message" role="status">{{ session('cart_notice') }} <a href="{{ route('storefront.cart.index') }}">View cart →</a></div>@endif
@if($errors->any())<div class="cart-message cart-error" role="alert">@foreach($errors->all() as $message)<p>{{ $message }}</p>@endforeach</div>@endif
@yield('content')</main>
<footer><div class="wrap nova-footer"><div class="footer-brand"><a class="logo" href="{{ route('home') }}">E24<span>®</span></a><p>Your destination for everyday discoveries.<br>Find something that fits your life.</p></div><div><h2>Explore</h2><a href="{{ route('home') }}">Home</a><a href="{{ route('storefront.products.index') }}">All products</a><a href="{{ route('storefront.products.index', ['sort' => 'newest']) }}">New arrivals</a></div><div><h2>Browse your way</h2><a href="{{ route('storefront.products.index', ['availability' => 'in_stock']) }}">In-stock products</a><a href="{{ route('storefront.products.index', ['sort' => 'price_low']) }}">Price: low to high</a><a href="{{ route('storefront.products.index', ['sort' => 'price_high']) }}">Price: high to low</a></div><div><h2>Our catalog</h2><p>Product details, available options<br>and stock in one place.</p><a href="{{ route('storefront.products.index') }}">Find your next favorite →</a></div></div><div class="wrap footer-bottom"><small>© {{ date('Y') }} E24. All rights reserved.</small><span>Everyday finds. More possibilities.</span></div></footer>
</body></html>
