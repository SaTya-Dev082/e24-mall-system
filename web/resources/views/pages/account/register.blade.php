<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Register — E24</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="login-page">
    <main class="login-panel" id="main-content">
        <a class="login-brand" href="{{ route('home') }}" aria-label="E24 home">E24<span>®</span></a>
        <h1>Register</h1>
        <p class="login-intro">Create your account to get started</p>
        @if($errors->any())
            <div class="login-errors" data-auth-server-errors role="alert">@foreach($errors->all() as $message)<p>{{ $message }}</p>@endforeach</div>
        @endif
        <div class="login-errors" data-auth-errors role="alert" tabindex="-1" hidden></div>
        <form method="POST" action="{{ route('register.store') }}" class="login-form" data-auth-form>
            @csrf
            <label for="register-name">Full name</label>
            <input id="register-name" name="name" value="{{ old('name') }}" placeholder="Enter your full name" autocomplete="name" maxlength="255" required autofocus @error('name') aria-invalid="true" aria-describedby="register-name-error" @enderror>
            @error('name')<p class="auth-field-error" data-auth-field-error="name" id="register-name-error">{{ $message }}</p>@enderror
            <label for="register-email">Email address</label>
            <input id="register-email" type="email" name="email" value="{{ old('email') }}" placeholder="you@example.com" autocomplete="email" maxlength="255" required @error('email') aria-invalid="true" aria-describedby="register-email-error" @enderror>
            @error('email')<p class="auth-field-error" data-auth-field-error="email" id="register-email-error">{{ $message }}</p>@enderror
            <label for="register-phone">Phone <span>(optional)</span></label>
            <input id="register-phone" type="tel" name="phone" value="{{ old('phone') }}" placeholder="Enter your phone number" autocomplete="tel" maxlength="50" @error('phone') aria-invalid="true" aria-describedby="register-phone-error" @enderror>
            @error('phone')<p class="auth-field-error" data-auth-field-error="phone" id="register-phone-error">{{ $message }}</p>@enderror
            <label for="register-password">Password</label>
            <input id="register-password" type="password" name="password" placeholder="8–256 characters" autocomplete="new-password" minlength="8" maxlength="256" required @error('password') aria-invalid="true" aria-describedby="register-password-error" @enderror>
            @error('password')<p class="auth-field-error" data-auth-field-error="password" id="register-password-error">{{ $message }}</p>@enderror
            <label for="register-password-confirmation">Confirm password</label>
            <input id="register-password-confirmation" type="password" name="password_confirmation" placeholder="Re-enter your password" autocomplete="new-password" minlength="8" maxlength="256" required>
            @error('password_confirmation')<p class="auth-field-error" data-auth-field-error="password_confirmation" id="register-password-confirmation-error">{{ $message }}</p>@enderror
            <button type="submit">Register</button>
        </form>
        <p class="login-register">Already have an account? <a href="{{ route('login') }}">Log in</a></p>
        @include('components.auth-social')
    </main>
</body>
</html>
