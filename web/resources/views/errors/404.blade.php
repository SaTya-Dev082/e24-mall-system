@extends('layouts.storefront')
@section('title', 'Page not found — E24')
@section('content')<section class="empty"><h1>We couldn’t find that page.</h1><p>This product may no longer be available.</p><a class="button" href="{{ route('storefront.products.index') }}">Explore products ↗</a></section>@endsection
