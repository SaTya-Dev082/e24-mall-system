@extends('layouts.storefront')
@section('title', 'Choose new password — E24')
@section('content')
<section class="customer-auth"><h1>New password</h1><form method="POST" action="{{ route('password.update') }}">@csrf<input type="hidden" name="token" value="{{ $token }}"><label>Email<input type="email" name="email" value="{{ $email }}" required maxlength="255" autocomplete="email"></label><label>New password<input type="password" name="password" minlength="8" maxlength="256" autocomplete="new-password" required></label><label>Confirm password<input type="password" name="password_confirmation" minlength="8" maxlength="256" autocomplete="new-password" required></label><button class="button">Reset password</button></form></section>
@endsection
