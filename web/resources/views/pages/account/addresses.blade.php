@extends('layouts.storefront')
@section('title', 'My addresses — E24')
@section('content')
<section class="customer-account"><div class="section-heading"><h1>My addresses</h1><a href="{{ route('account.addresses.create') }}">Add address →</a></div><div class="address-list">@forelse($addresses as $address)<article><h2>{{ $address->full_name }} @if($address->is_default)<small>Default</small>@endif</h2><p>{{ $address->phone }}<br>{{ $address->street_address }}<br>{{ $address->city }} {{ $address->province }} {{ $address->postal_code }}</p><a href="{{ route('account.addresses.edit', $address->id) }}">Edit</a><form method="POST" action="{{ route('account.addresses.remove', $address->id) }}">@csrf @method('DELETE')<button>Remove</button></form></article>@empty<p>No addresses yet. Add a delivery address to prepare for checkout.</p>@endforelse</div></section>
@endsection
