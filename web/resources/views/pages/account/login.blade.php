<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Log In — E24</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="login-page">
    <main class="login-panel" id="main-content">
        <a class="login-brand" href="{{ route('home') }}" aria-label="E24 home">E24<span>®</span></a>
        <h1>Log In</h1>
        <p class="login-intro">Please enter your login details below</p>
        @if(session('account_status'))
            <p class="login-notice" role="status">{{ session('account_status') }}</p>
        @endif
        @if($errors->any())
            <div class="login-errors" data-auth-server-errors role="alert">@foreach($errors->all() as $message)<p>{{ $message }}</p>@endforeach</div>
        @endif
        <div class="login-errors" data-auth-errors role="alert" tabindex="-1" hidden></div>
        <form method="POST" action="{{ route('login.store') }}" class="login-form" data-auth-form>
            @csrf
            <label for="login-email">Email address</label>
            <input id="login-email" type="email" name="email" value="{{ old('email') }}" placeholder="you@example.com" autocomplete="username" maxlength="255" required autofocus @error('email') aria-invalid="true" aria-describedby="login-email-error" @enderror>
            @error('email')<p class="auth-field-error" data-auth-field-error="email" id="login-email-error">{{ $message }}</p>@enderror
            <label for="login-password">Password</label>
            <input id="login-password" type="password" name="password" placeholder="Enter your password" autocomplete="current-password" maxlength="256" required>
            @error('password')<p class="auth-field-error" data-auth-field-error="password" id="login-password-error">{{ $message }}</p>@enderror
            <div class="login-options">
                <label class="login-remember"><input type="checkbox" name="remember" value="1" @checked(old('remember'))> <span>Remember me</span></label>
                <a href="{{ route('password.request') }}">Forgot password?</a>
            </div>
            <button type="submit">Log in</button>
        </form>
        <p class="login-register">Don't have an account? <a href="{{ route('register') }}">Register</a></p>
        @include('components.auth-social')
    </main>
</body>
</html>
