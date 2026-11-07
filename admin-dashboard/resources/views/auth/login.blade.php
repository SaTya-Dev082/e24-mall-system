@extends('layouts.admin')
@section('title', 'Sign in')
@section('content')
<main class="login-shell">
    <section class="brand-panel" aria-label="Shop administration">
        <a class="brand brand-light" href="{{ route('login') }}"><span class="brand-icon">E</span> E24<span class="brand-tag">ADMIN</span></a>
        <div class="brand-story"><span class="eyebrow">YOUR STORE, CONNECTED</span><h1>A little clarity.<br>A better workday.</h1><p>One place to manage your store and keep your next move in focus.</p>
            <div class="store-art" aria-hidden="true"><div class="art-top"><span>YOUR WORKSPACE</span><i></i><i></i><i></i></div><div class="art-body"><div class="art-sidebar"><b></b><b></b><b></b></div><div class="art-content"><div class="art-bars"><i></i><i></i><i></i><i></i><i></i><i></i><i></i></div><div class="art-line"></div><div class="art-line short"></div></div></div><div class="art-note"><span>✓</span> Everything in its place.</div></div>
        </div>
        <p class="brand-footer">Built for the everyday work of running a store.</p>
    </section>
    <section class="form-panel">
        <div class="login-form"><span class="pill">STORE ADMINISTRATION</span><h2>Welcome back.</h2><p class="muted intro">Sign in to your store workspace.</p>
            @if (session('status'))<div class="notice" role="status">{{ session('status') }}</div>@endif
            @if ($errors->any())<div class="error-box" role="alert">{{ $errors->first() }}</div>@endif
            <form method="POST" action="{{ route('login.store') }}">
                @csrf
                <label for="email">Email address</label>
                <input id="email" name="email" type="email" value="{{ old('email') }}" placeholder="you@yourstore.com" autocomplete="username" maxlength="255" required autofocus @if($errors->has('email')) aria-invalid="true" @endif>
                <label for="password">Password</label>
                <input id="password" name="password" type="password" placeholder="Enter your password" autocomplete="current-password" maxlength="128" required>
                <button class="button" type="submit">Sign in <span aria-hidden="true">→</span></button>
            </form>
            <p class="access-note"><span aria-hidden="true">◇</span> Access is reserved for active administrators.</p>
            <div class="login-help">Need access to this workspace?<br><span>Contact your store administrator.</span></div>
        </div>
        <p class="page-footer">E24 Admin <span>·</span> Store workspace</p>
    </section>
</main>
@endsection
