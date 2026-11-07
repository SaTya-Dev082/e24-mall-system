@extends('layouts.storefront')
@section('title', 'Verify email — E24')
@section('content')
<section class="customer-auth"><h1>Verify your email</h1><p>Use the verification link sent to your account email before continuing to checkout.</p><form method="POST" action="{{ route('verification.send') }}">@csrf<button class="button">Resend verification email</button></form><p><a href="{{ route('account.profile') }}">My profile →</a></p></section>
@endsection
