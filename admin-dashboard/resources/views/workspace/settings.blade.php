@extends('layouts.workspace')
@section('title','Account')
@section('workspace')
<div class="nx-page-title">
    <div><h1>Account</h1><p>Your administrator profile and access.</p></div>
</div>
<section class="nx-panel">
    <div class="nx-panel-heading"><h2>Administrator account</h2><span class="nx-badge completed">Active</span></div>
    <dl class="nx-settings-details">
        <div><dt>Name</dt><dd>{{ auth()->user()->name }}</dd></div>
        <div><dt>Email</dt><dd>{{ auth()->user()->email }}</dd></div>
        <div><dt>Role</dt><dd>Administrator</dd></div>
    </dl>
    <form method="POST" action="{{ route('logout') }}" class="nx-panel-pad">
        @csrf
        <button type="submit" class="nx-button secondary"><x-admin-icon name="logout" /> Sign out securely</button>
    </form>
</section>
@endsection
