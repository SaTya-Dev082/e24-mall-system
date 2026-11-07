@extends('layouts.storefront')
@section('title', 'Sign in — E24')
@section('content')
<section class="customer-auth"><p class="eyebrow">WELCOME BACK</p><h1>Sign in</h1><p>Keep your cart and continue to checkout.</p><form method="POST" action="{{ route('login.store') }}">@csrf<label>Email<input type="email" name="email" value="{{ old('email') }}" autocomplete="username" maxlength="255" required autofocus></label><label>Password<input type="password" name="password" autocomplete="current-password" maxlength="128" required></label><button class="button">Sign in</button></form><p><a href="{{ route('password.request') }}">Forgot password?</a></p><p>New to E24? <a href="{{ route('register') }}">Create an account</a></p></section>
@endsection
