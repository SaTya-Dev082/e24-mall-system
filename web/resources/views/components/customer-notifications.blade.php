<details class="header-notifications" @if($customer) data-customer-notifications data-feed-url="{{ route('storefront.notifications.feed') }}" @endif>
    <summary aria-label="Notifications{{ $customerUnreadCount ? ', '.$customerUnreadCount.' unread' : '' }}" title="Notifications">
        @include('components.notification-symbol')
        @if($customer)<span class="notification-count" data-notification-count @if(!$customerUnreadCount) hidden @endif aria-hidden="true">{{ $customerUnreadCount > 99 ? '99+' : $customerUnreadCount }}</span>@endif
    </summary>
    <div class="notification-popover">
        <div class="notification-popover-heading"><div><h2>Notifications</h2><p>Stay up to date with E24</p></div>@if($customer)<span class="notification-new-pill" data-notification-new-pill @if(!$customerUnreadCount) hidden @endif>{{ $customerUnreadCount }} new</span>@endif</div>
        @if($customer)
            <div class="notification-popover-toolbar"><span>Latest updates</span><form method="POST" action="{{ route('storefront.notifications.read-all') }}" data-notification-read-all>@csrf<button type="submit" @disabled(!$customerUnreadCount)>Mark all as read</button></form></div>
            <p class="notification-status" data-notification-status role="status"></p>
            <div class="notification-feed-scroll" data-notification-items><p>Loading your updates…</p></div>
            <template data-notification-symbol>@include('components.notification-symbol')</template>
            <a class="notification-view-all" href="{{ route('storefront.notifications.index') }}">View all notifications <span aria-hidden="true">→</span></a>
        @else
            <div class="notification-empty"><span class="notification-empty-symbol">@include('components.notification-symbol')</span><h3>Your updates live here</h3><p>Log in to see messages from E24.</p></div><a class="notification-view-all" href="{{ route('login') }}">Log in <span aria-hidden="true">→</span></a>
        @endif
    </div>
</details>
