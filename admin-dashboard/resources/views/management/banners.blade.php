@extends('layouts.workspace')
@section('title', 'Homepage banners')
@push('styles')<link rel="stylesheet" href="{{ asset('css/banners.css') }}">@endpush
@section('workspace')
<div class="nx-page-title"><div><h1>Homepage banners</h1><p>Storefront campaigns</p></div><a class="nx-button" href="{{ route('management.create', 'banners') }}"><x-admin-icon name="plus" /> Add banner</a></div>
<form class="nx-filter-form" method="GET">
    <input type="search" name="search" value="{{ request('search') }}" maxlength="100" placeholder="Search banners" aria-label="Search banners">
    <select name="archived" aria-label="Archive filter"><option value="">Current banners</option><option value="1" @selected(request('archived')==='1')>Archived banners</option></select>
    <button class="nx-button secondary"><x-admin-icon name="search" /> Filter</button><a class="nx-text-link" href="{{ route('management.index', 'banners') }}">Reset</a>
</form>
<div class="e24-banner-list">
@forelse($rows as $banner)
    @php
        $state = $banner->deleted_at ? 'Archived' : ($banner->status !== 'active' ? 'Inactive' : ($banner->start_date && \Illuminate\Support\Carbon::parse($banner->start_date)->isFuture() ? 'Scheduled' : ($banner->end_date && \Illuminate\Support\Carbon::parse($banner->end_date)->lte(now()) ? 'Expired' : 'Live')));
    @endphp
    <article class="e24-banner-row">
        <div class="e24-banner-thumbnail"><img src="{{ $banner->image_url }}" alt="{{ $banner->alt_text }}" loading="lazy" referrerpolicy="no-referrer"></div>
        <div class="e24-banner-info"><span class="e24-banner-state" data-state="{{ strtolower($state) }}">{{ $state }}</span><h2>{{ $banner->name }}</h2><p>{{ $banner->alt_text }}</p><dl><div><dt>Display order</dt><dd>{{ $banner->sort_order }}</dd></div><div><dt>Starts (UTC)</dt><dd>{{ $banner->start_date ? \Illuminate\Support\Carbon::parse($banner->start_date)->format('d M Y, H:i') : 'Immediately' }}</dd></div><div><dt>Ends (UTC)</dt><dd>{{ $banner->end_date ? \Illuminate\Support\Carbon::parse($banner->end_date)->format('d M Y, H:i') : 'No end date' }}</dd></div></dl></div>
        <div class="nx-row-actions">
        @if($banner->deleted_at)
            <form method="POST" action="{{ route('management.restore', ['banners', $banner->id]) }}">@csrf @method('PATCH')<button class="nx-button secondary small">Restore</button></form>
        @else
            <a class="nx-icon-button" href="{{ route('management.edit', ['banners', $banner->id]) }}" title="Edit banner" aria-label="Edit {{ $banner->name }}"><x-admin-icon name="edit" /></a>
            <form method="POST" action="{{ route('management.archive', ['banners', $banner->id]) }}" data-confirm="Archive this banner? It will no longer appear on the storefront.">@csrf @method('DELETE')<button class="nx-icon-button danger" title="Archive banner" aria-label="Archive {{ $banner->name }}"><x-admin-icon name="trash" /></button></form>
        @endif
        </div>
    </article>
@empty
    <div class="nx-empty"><h3>No banners found</h3><p>{{ request('archived') ? 'No archived banners match this search.' : 'Your next campaign starts here.' }}</p></div>
@endforelse
</div>
<x-workspace-pagination :paginator="$rows" />
@endsection
