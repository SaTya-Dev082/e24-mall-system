@extends('layouts.workspace')
@section('title', 'Customer notifications')
@push('scripts')<script src="{{ asset('js/customer-announcements.js') }}?v={{ filemtime(public_path('js/customer-announcements.js')) }}" defer></script>@endpush
@section('workspace')
<div class="nx-page-title"><div><h1>Customer notifications</h1><p>Send store updates to a customer or all current active customers. These appear inside the website notification bell.</p></div></div>
<section class="nx-panel nx-form-panel">
    <form method="POST" action="{{ route('customer-announcements.store') }}" data-announcement-form>
        @csrf<input type="hidden" name="request_key" value="{{ old('request_key', $requestKey) }}">
        <div class="nx-form-grid">
            <div><label for="announcement-audience">Send to</label><select id="announcement-audience" name="audience"><option value="customer" @selected(old('audience') !== 'all')>One customer</option><option value="all" @selected(old('audience') === 'all')>All active customers</option></select></div>
            <div><label for="announcement-action">Open when clicked</label><select name="action" id="announcement-action">@foreach(['products'=>'All products','home'=>'Home','cart'=>'Shopping cart','profile'=>'My profile'] as $value=>$label)<option value="{{ $value }}" @selected(old('action','products') === $value)>{{ $label }}</option>@endforeach</select></div>
            <div class="nx-full-width" data-announcement-recipient>
                <label for="announcement-search">Find customer</label><input type="search" id="announcement-search" placeholder="Search by name or email" maxlength="100" data-search-url="{{ route('customer-announcements.recipients') }}">
                <label for="announcement-recipient">Customer</label><select name="recipient_id" id="announcement-recipient"><option value="">Select a customer</option>@foreach($customers as $customer)<option value="{{ $customer->id }}" @selected((string) old('recipient_id') === (string) $customer->id)>{{ $customer->name }} · {{ $customer->email }}</option>@endforeach</select><p class="nx-field-help" data-recipient-status role="status">Showing up to 30 matching customers.</p>
            </div>
            <div class="nx-full-width"><label for="announcement-title">Title</label><input id="announcement-title" name="title" maxlength="120" value="{{ old('title') }}" required></div>
            <div class="nx-full-width"><label for="announcement-message">Message</label><textarea id="announcement-message" name="message" maxlength="1000" rows="4" required>{{ old('message') }}</textarea><p class="nx-field-help">Plain text, up to 1,000 characters. No email or browser push is sent.</p></div>
        </div>
        <div class="nx-dialog-footer"><button class="nx-button" type="submit" data-busy-submit>Send notification</button></div>
    </form>
</section>
<section class="nx-panel" style="margin-top:24px">
    <div class="nx-panel-heading"><h2>Delivery history</h2><a class="nx-button secondary small" href="{{ route('customer-announcements.index') }}">Refresh status</a></div>
    <div class="nx-table-wrap"><table class="nx-table"><thead><tr><th>Title</th><th>Audience</th><th>Status</th><th>Delivered</th><th>Created</th><th></th></tr></thead><tbody>
        @forelse($campaigns as $campaign)<tr><td>{{ $campaign->title }}</td><td>{{ $campaign->audience === 'all' ? 'All active customers' : 'Customer #'.$campaign->recipient_id }}</td><td><span class="nx-badge {{ $campaign->status === 'completed' ? 'completed' : ($campaign->status === 'failed' ? 'cancelled' : 'pending') }}">{{ ucfirst($campaign->status) }}</span></td><td>{{ number_format($campaign->delivered_count) }}</td><td>{{ \Illuminate\Support\Carbon::parse($campaign->created_at)->timezone('Asia/Phnom_Penh')->format('d M Y, H:i') }}</td><td>@if($campaign->status === 'failed')<form method="POST" action="{{ route('customer-announcements.retry', $campaign->id) }}">@csrf<button class="nx-button secondary small" type="submit">Retry delivery</button></form>@endif</td></tr>
        @empty<tr><td colspan="6">No customer notifications have been sent yet.</td></tr>@endforelse
    </tbody></table></div>
    <x-workspace-pagination :paginator="$campaigns" />
</section>
@endsection
