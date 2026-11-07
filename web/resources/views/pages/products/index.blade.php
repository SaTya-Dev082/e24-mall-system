@extends('layouts.storefront')
@section('title', 'Explore products — E24')
@section('content')
<div class="page-heading"><p class="eyebrow">THE E24 CATALOG</p><h1>Find your everyday.</h1><p>{{ $products->total() }} products to explore</p></div>
<div class="catalog catalog-full"><div><div class="product-grid listing">@forelse($products as $product)<x-product-card :product="$product" />@empty<div class="empty"><h2>No products found</h2><p>Try another keyword in the search field above.</p><a href="{{ route('storefront.products.index') }}">View all products ↗</a></div>@endforelse</div><div class="pagination">{{ $products->links() }}</div></div></div>
@endsection
