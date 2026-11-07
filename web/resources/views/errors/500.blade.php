@extends('layouts.storefront')
@section('title', 'Something went wrong — E24')
@section('content')<section class="empty"><h1>Something went wrong.</h1><p>Please try again shortly.</p><a href="{{ route('home') }}">Return home</a></section>@endsection
