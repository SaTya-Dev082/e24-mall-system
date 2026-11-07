@extends('layouts.storefront')
@section('title', 'Check your filters — E24')
@section('content')<section class="empty"><h1>Check your search filters</h1><ul>@foreach($messages as $message)<li>{{ $message }}</li>@endforeach</ul><a class="button" href="{{ route('storefront.products.index') }}">Back to products</a></section>@endsection
