@extends('layouts.workspace')
@section('title','Notifications')
@section('workspace')
<div class="nx-page-title">
    <div><h1>Notifications</h1><p>System alerts and updates for the parts of the store you manage.</p></div>
    <a class="nx-button secondary" href="{{ route('notifications',['type'=>$type]) }}"><x-admin-icon name="refresh" /> Refresh</a>
</div>
<nav class="nx-notification-filters" aria-label="Notification categories">
    @foreach($categories as $key=>$label)
        <a href="{{ route('notifications',['type'=>$key]) }}" class="nx-notification-filter {{ $type===$key?'selected':'' }}" @if($type===$key) aria-current="page" @endif>{{ $label }}@if($key!=='activity')<span>{{ $notificationSummary[$key] }}</span>@endif</a>
    @endforeach
</nav>
<section class="nx-panel">
    <div class="nx-panel-heading"><h2>{{ $categories[$type] }}</h2><span class="nx-muted">{{ number_format($items->total()) }} {{ $type==='activity'?'events':'active alerts' }}</span></div>
    @forelse($items as $notification)
        <article class="nx-notification-row">
            <span class="nx-notification-symbol {{ $notification->level }}"><x-admin-icon :name="$notification->icon" /></span>
            <div class="nx-notification-body"><h3>{{ $notification->title }}</h3><p>{{ $notification->message }}</p><div><span class="nx-badge {{ $notification->level==='error'?'cancelled':($notification->level==='warning'?'pending':'processing') }}">{{ $notification->level==='error'?'Needs attention':ucfirst($notification->category) }}</span>@if($notification->occurred_at)<time datetime="{{ \Illuminate\Support\Carbon::parse($notification->occurred_at)->toIso8601String() }}">{{ \Illuminate\Support\Carbon::parse($notification->occurred_at)->timezone('Asia/Phnom_Penh')->format('M d, Y · H:i') }}</time>@endif</div></div>
            <a class="nx-button secondary small" href="{{ $notification->url }}">View <x-admin-icon name="arrow" /></a>
        </article>
    @empty
        <div class="nx-empty"><x-admin-icon name="check" /><h3>{{ $type==='activity'?'No system activity recorded':'You’re all caught up' }}</h3><p>{{ $type==='activity'?'Successful administrator changes will appear here.':'No active alerts in your permitted modules.' }}</p></div>
    @endforelse
    <x-workspace-pagination :paginator="$items" />
    <p class="nx-panel-note">Alerts update when the underlying issue is resolved. These are active issue counts, not unread messages. Times are shown in Asia/Phnom Penh.</p>
</section>
@endsection
