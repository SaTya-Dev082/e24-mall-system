@extends('layouts.admin')
@push('styles')
<link rel="stylesheet" href="{{ asset('css/workspace.css') }}?v={{ filemtime(public_path('css/workspace.css')) }}">
<script src="{{ asset('js/workspace.js') }}?v={{ filemtime(public_path('js/workspace.js')) }}" defer></script>
@endpush
@section('content')
<noscript><p class="nx-noscript">Enable JavaScript to use stock dialogs, theme controls, and the mobile menu. Search, reports, and product forms work without it.</p></noscript>
<div class="nexora" data-theme="dark">
    <a class="skip-link" href="#workspace-content">Skip to content</a>
    <aside class="nx-sidebar" id="workspace-navigation">
        <div class="nx-brand-row">
            <a class="nx-brand" href="{{ auth()->user()->workspaceHome() }}" aria-label="E24 dashboard"><span class="nx-logo" aria-hidden="true">⬡</span><span class="nx-brand-name">E24</span></a>
            <button class="nx-icon-button" type="button" data-collapse aria-label="Toggle sidebar" aria-expanded="true"><x-admin-icon name="collapse" /></button>
        </div>
        @php
            $currentResource = request()->route('resource');
            $currentModule = $currentResource ? config('admin_resources.'.$currentResource.'.module') : null;
            $catalogActive = request()->routeIs('products', 'products.*','variants','variants.*','images','images.*') || $currentModule === 'catalog';
            $inventoryActive = request()->routeIs('inventory', 'inventory.*');
            $groups = [
                ['id' => 'dashboard', 'label' => 'Dashboard', 'icon' => 'overview', 'active' => request()->routeIs('dashboard'), 'links' => [
                    ['label' => 'Overview', 'url' => route('dashboard'), 'active' => request()->routeIs('dashboard'), 'section' => 'overview'],
                    ['label' => 'Analytics', 'url' => route('dashboard').'#analytics', 'active' => false, 'section' => 'analytics'],
                ]],
                ['id' => 'catalog', 'label' => 'Products', 'icon' => 'box', 'active' => $catalogActive, 'links' => [
                    ['label' => 'All products', 'url' => route('products'), 'active' => request()->routeIs('products', 'products.edit','variants','variants.*','images','images.*') && !request()->boolean('archived')],
                    ['label' => 'Add product', 'url' => route('products.create'), 'active' => request()->routeIs('products.create')],
                    ['label' => 'Archived products', 'url' => route('products', ['archived' => 1]), 'active' => request()->routeIs('products') && request()->boolean('archived')],
                    ['label' => 'Categories', 'url' => route('management.index','categories'), 'active' => $currentResource === 'categories'],
                    ['label' => 'Brands', 'url' => route('management.index','brands'), 'active' => $currentResource === 'brands'],
                    ['label' => 'Attributes', 'url' => route('management.index','attributes'), 'active' => $currentResource === 'attributes'],
                    ['label' => 'Attribute values', 'url' => route('management.index','attribute-values'), 'active' => $currentResource === 'attribute-values'],
                ]],
                ['id' => 'inventory', 'label' => 'Inventory', 'icon' => 'layers', 'active' => $inventoryActive, 'count' => $alertCount, 'links' => [
                    ['label' => 'Stock overview', 'url' => route('inventory'), 'active' => request()->routeIs('inventory','inventory.history') && !in_array(request('stock'), ['low', 'out'], true)],
                    ['label' => 'Low stock', 'url' => route('inventory', ['stock' => 'low']), 'active' => request()->routeIs('inventory') && request('stock') === 'low'],
                    ['label' => 'Out of stock', 'url' => route('inventory', ['stock' => 'out']), 'active' => request()->routeIs('inventory') && request('stock') === 'out'],
                    ['label' => 'Reservations', 'url' => route('inventory.reservations'), 'active' => request()->routeIs('inventory.reservations')],
                ]],
                ['id' => 'sales', 'label' => 'Orders', 'icon' => 'orders', 'active' => request()->routeIs('orders', 'orders.*'), 'links' => [
                    ['label' => 'All orders', 'url' => route('orders'), 'active' => request()->routeIs('orders', 'orders.*') && request('status') !== 'pending'],
                    ['label' => 'Pending orders', 'url' => route('orders', ['status' => 'pending']), 'active' => request()->routeIs('orders') && request('status') === 'pending'],
                ]],
                ['id' => 'customers', 'label' => 'Customers', 'icon' => 'users', 'active' => request()->routeIs('customers','customers.*'), 'links' => [
                    ['label' => 'All customers', 'url' => route('customers'), 'active' => request()->routeIs('customers','customers.*')],
                ]],
                ['id'=>'payments','label'=>'Payments','icon'=>'wallet','active'=>request()->routeIs('payments','refunds','refunds.*'),'links'=>[
                    ['label'=>'Payment records','url'=>route('payments'),'active'=>request()->routeIs('payments')],
                    ['label'=>'Refund requests','url'=>route('refunds'),'active'=>request()->routeIs('refunds','refunds.*')],
                ]],
                ['id'=>'promotions','label'=>'Promotions','icon'=>'tag','active'=>$currentModule==='promotions','links'=>[
                    ['label'=>'Banners','url'=>route('management.index','banners'),'active'=>$currentResource==='banners'],
                    ['label'=>'Coupons','url'=>route('management.index','coupons'),'active'=>$currentResource==='coupons'],
                ]],
                ['id'=>'shipping','label'=>'Shipping','icon'=>'store','active'=>$currentModule==='shipping','links'=>[
                    ['label'=>'Shipping methods','url'=>route('management.index','shipping-methods'),'active'=>$currentResource==='shipping-methods'],
                    ['label'=>'Service areas & rates','url'=>route('management.index','shipping-zones'),'active'=>$currentResource==='shipping-zones'],
                ]],
                ['id'=>'reviews','label'=>'Reviews','icon'=>'spark','active'=>$currentModule==='reviews','links'=>[
                    ['label'=>'Review moderation','url'=>route('management.index','reviews'),'active'=>$currentResource==='reviews'],
                ]],
                ['id' => 'reports', 'label' => 'Reports', 'icon' => 'report', 'active' => request()->routeIs('reports', 'reports.*'), 'links' => [
                    ['label' => 'Sales reports', 'url' => route('reports'), 'active' => request()->routeIs('reports') && !request('type')],
                    ['label'=>'Inventory reports','url'=>route('reports',['type'=>'inventory']),'active'=>request('type')==='inventory','permission'=>'inventory'],
                    ['label'=>'Product reports','url'=>route('reports',['type'=>'products']),'active'=>request('type')==='products','permission'=>'catalog'],
                    ['label'=>'Customer reports','url'=>route('reports',['type'=>'customers']),'active'=>request('type')==='customers','permission'=>'customers'],
                    ['label'=>'Payment reports','url'=>route('reports',['type'=>'payments']),'active'=>request('type')==='payments','permission'=>'payments'],
                    ['label'=>'Refund reports','url'=>route('reports',['type'=>'refunds']),'active'=>request('type')==='refunds','permission'=>'payments'],
                ]],
                ['id'=>'administration','label'=>'Administration','icon'=>'shield','active'=>request()->routeIs('staff','staff.*','audit','store-settings','store-settings.*'),'links'=>[
                    ['label'=>'Staff & permissions','url'=>route('staff'),'active'=>request()->routeIs('staff','staff.*')],
                    ['label'=>'Audit history','url'=>route('audit'),'active'=>request()->routeIs('audit')],
                    ['label'=>'Store settings','url'=>route('store-settings'),'active'=>request()->routeIs('store-settings','store-settings.*')],
                ]],
                ['id' => 'settings', 'label' => 'Settings', 'icon' => 'settings', 'active' => request()->routeIs('settings','settings.*'), 'links' => [
                    ['label' => 'Account', 'url' => route('settings.account'), 'active' => request()->routeIs('settings','settings.account')],
                    ['label' => 'Appearance', 'url' => route('settings.appearance'), 'active' => request()->routeIs('settings.appearance')],
                ]],
            ];
            $notificationLinks=[];
            foreach(app(\App\Services\SystemNotifications::class)->categories() as $key=>$label){
                $notificationLinks[]=['label'=>$label,'url'=>route('notifications',$key==='all'?[]:['type'=>$key]),'active'=>request()->routeIs('notifications')&&request('type','all')===$key];
            }
            array_splice($groups,1,0,[['id'=>'notifications','label'=>'Notifications','icon'=>'bell','count'=>$notificationCount,'active'=>request()->routeIs('notifications'),'links'=>$notificationLinks]]);
        @endphp
        <nav aria-label="Store navigation" class="nx-grouped-nav">
            @foreach ($groups as $group)
                @php($permission = match($group['id']){'dashboard'=>'reports','sales'=>'orders','settings','notifications'=>null,default=>$group['id']})
                @continue($permission && !auth()->user()->canManage($permission))
                <details name="workspace-navigation" class="nx-nav-group {{ $group['active'] ? 'has-active-page' : '' }}" data-nav-group="{{ $group['id'] }}" @if($group['active']) open @endif>
                    <summary class="nx-nav-heading nx-nav-link {{ $group['active'] ? 'is-active' : '' }}" title="{{ $group['label'] }}">
                        <x-admin-icon :name="$group['icon']" />
                        <span class="nx-nav-title">{{ $group['label'] }}</span>
                        @if(!empty($group['count']))<small class="nx-nav-count">{{ $group['count'] }}</small>@endif
                        <x-admin-icon name="chevron" class="nx-nav-chevron" />
                    </summary>
                    <div class="nx-submenu">
                        @foreach($group['links'] as $link)
                            @continue(isset($link['permission']) && !auth()->user()->canManage($link['permission']))
                            <a href="{{ $link['url'] }}" class="nx-submenu-link {{ $link['active'] ? 'is-active' : '' }}" @if($link['active']) aria-current="page" @endif @isset($link['section']) data-nav-section="{{ $link['section'] }}" @endisset>{{ $link['label'] }}</a>
                        @endforeach
                    </div>
                </details>
            @endforeach
        </nav>
    </aside>
    <div class="nx-main">
        <header class="nx-topbar">
            <button class="nx-icon-button nx-mobile-menu" data-mobile-menu type="button" aria-label="Open navigation" aria-expanded="false" aria-controls="workspace-navigation"><x-admin-icon name="menu" /></button>
            @if(auth()->user()->canManage('catalog'))<form action="{{ route('products') }}" class="nx-global-search" role="search"><x-admin-icon name="search" /><input name="search" type="search" maxlength="100" placeholder="Search products or SKU…" value="{{ request()->routeIs('products') ? request('search') : '' }}" aria-label="Search products" data-search><kbd>⌘ K</kbd></form>@else<span class="nx-muted">E24 workspace</span>@endif
            <div class="nx-topbar-actions">
                @if(auth()->user()->canManage('reports'))
                <a class="nx-date-short" href="{{ route('reports') }}" title="Choose reporting dates"><x-admin-icon name="calendar" /><span>{{ now()->timezone('Asia/Phnom_Penh')->format('M d, Y') }}</span></a>
                @endif
                <details class="nx-popover"><summary class="nx-icon-button" aria-label="System notifications"><x-admin-icon name="bell" />@if($notificationCount)<span class="nx-notification-count">{{ $notificationCount > 99 ? '99+' : $notificationCount }}</span>@endif</summary><div class="nx-dropdown nx-notifications"><h3>System notifications <span>{{ $notificationCount }}</span></h3>@forelse($notificationPreview as $notification)<a href="{{ $notification->url }}"><span class="nx-mini-icon {{ $notification->level==='error'?'color-3':($notification->level==='warning'?'amber':'color-0') }}"><x-admin-icon :name="$notification->icon" /></span><span><strong>{{ $notification->title }}</strong><small>{{ \Illuminate\Support\Str::limit($notification->message,65) }}</small></span></a>@empty<p class="nx-empty-small">No active alerts in your permitted modules.</p>@endforelse<a class="nx-dropdown-footer" href="{{ route('notifications') }}">View all notifications <x-admin-icon name="arrow" /></a></div></details>
                @if(auth()->user()->canManage('reports'))
                <a class="nx-icon-button" href="{{ route('reports') }}" aria-label="Open reports"><x-admin-icon name="report" /></a>
                @endif
                <details class="nx-popover nx-account"><summary><span class="nx-avatar">{{ mb_strtoupper(mb_substr(auth()->user()->name,0,1)) }}</span><span class="nx-user-name"><strong>{{ auth()->user()->name }}</strong><small>Administrator</small></span><x-admin-icon name="chevron" /></summary><div class="nx-dropdown nx-user-menu"><p>{{ auth()->user()->email }}</p><a href="{{ route('settings.account') }}"><x-admin-icon name="users" /> Account</a><a href="{{ route('settings.appearance') }}"><x-admin-icon name="settings" /> Appearance</a><form method="POST" action="{{ route('logout') }}">@csrf<button type="submit"><x-admin-icon name="logout" /> Sign out</button></form></div></details>
            </div>
        </header>
        <main class="nx-content" id="workspace-content" tabindex="-1">
            @if(session('status'))<div class="nx-flash success" role="status"><x-admin-icon name="check" />{{ session('status') }}</div>@endif
            @if($errors->any())<div class="nx-flash error" role="alert"><div><strong>Please check your request.</strong>@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div></div>@endif
            @yield('workspace')
        </main>
        <footer class="nx-footer"><span>E24 Admin · {{ now()->year }}</span><span>One workspace. Every detail.</span></footer>
    </div>
</div>
@endsection
