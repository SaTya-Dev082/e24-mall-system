@extends('layouts.storefront')
@section('title', 'Reset password — E24')
@section('content')
<section class="customer-auth"><h1>Forgot password?</h1><p>Enter your customer email to request a password reset link.</p><form method="POST" action="{{ route('password.email') }}">@csrf<label>Email<input name="email" type="email" value="{{ old('email') }}" maxlength="255" autocomplete="email" required></label><button class="button">Request reset link</button></form><p><a href="{{ route('login') }}">Back to sign in</a></p></section>
@endsection
