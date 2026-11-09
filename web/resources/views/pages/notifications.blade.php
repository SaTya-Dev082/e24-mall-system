@extends('layouts.storefront')
@section('title', 'Notifications — E24')
@section('content')
<section class="customer-notification-page">
    <div class="notification-page-heading">
        <div class="notification-heading-copy"><span class="notification-heading-symbol">@include('components.notification-symbol')</span><div><h1>Notifications</h1><p>Your store updates, all in one place.</p></div></div>
        <form method="POST" action="{{ route('storefront.notifications.read-all') }}" data-notification-read-all>@csrf<button class="notification-mark-all" type="submit" @disabled(!$unreadCount)><svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="m3 12 4 4L17 6m-5 10 3 3L23 9" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>Mark all as read</button></form>
    </div>
    <p class="notification-page-status" data-notification-page-status role="status"></p>
    <div class="notification-inbox">
        <div class="notification-inbox-toolbar"><div><h2>Inbox</h2><span class="notification-new-pill" data-notification-new-pill @if(!$unreadCount) hidden @endif>{{ $unreadCount }} new</span></div><span>{{ number_format($notifications->total()) }} {{ Str::plural('update', $notifications->total()) }}</span></div>
        <div class="customer-notification-list">
            @forelse($notifications as $notification)
                <form method="POST" action="{{ route('storefront.notifications.read', $notification->id) }}" class="customer-notification-entry {{ $notification->read_at ? '' : 'is-unread' }}">
                    @csrf
                    <button type="submit">
                        <span class="notification-row-icon">@include('components.notification-symbol')</span>
                        <span class="notification-row-copy"><span class="notification-row-title"><strong>{{ $notification->data['title'] ?? 'Store update' }}</strong>@if(!$notification->read_at)<span class="notification-unread-dot" data-notification-unread-dot><span class="sr-only">Unread</span></span>@endif</span><span class="notification-row-message">{{ $notification->data['message'] ?? '' }}</span><span class="notification-row-meta"><span>E24 updates</span><span aria-hidden="true">·</span><time datetime="{{ $notification->created_at->toIso8601String() }}" title="{{ $notification->created_at->timezone('Asia/Phnom_Penh')->format('d M Y, H:i') }}">{{ $notification->created_at->diffForHumans() }}</time></span></span>
                        <svg class="notification-row-arrow" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="m9 6 6 6-6 6" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    </button>
                </form>
            @empty
                <div class="notification-empty"><span class="notification-empty-symbol">@include('components.notification-symbol')</span><h2>You're all caught up</h2><p>New store updates will appear here.</p><a href="{{ route('storefront.products.index') }}">Explore products →</a></div>
            @endforelse
        </div>
        @if($notifications->hasPages())<div class="notification-inbox-pagination">{{ $notifications->links() }}</div>@endif
    </div>
    <p class="notification-inbox-hint">Select an update to read it and view more.</p>
</section>
@endsection
