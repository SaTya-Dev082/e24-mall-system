@extends('layouts.workspace')
@section('title', $item ? 'Edit banner' : 'Add banner')
@push('styles')<link rel="stylesheet" href="{{ asset('css/banners.css') }}">@endpush
@push('scripts')<script src="{{ asset('js/banners.js') }}" defer></script>@endpush
@section('workspace')
<div class="nx-page-title"><div><a class="nx-back-link" href="{{ route('management.index', 'banners') }}">Back to banners</a><h1>{{ $item ? 'Edit banner' : 'Add banner' }}</h1></div></div>
<form method="POST" action="{{ $item ? route('management.update', ['banners', $item->id]) : route('management.store', 'banners') }}" class="e24-banner-editor" data-banner-editor>
@csrf @if($item) @method('PUT') @endif
<div class="e24-banner-fields">
    <h2>Campaign details</h2>
    @foreach(['name'=>'Campaign name', 'image_url'=>'Banner image URL', 'alt_text'=>'Image description', 'link_url'=>'Destination URL'] as $field=>$label)
    <div><label for="banner-{{ $field }}">{{ $label }}{{ $field !== 'link_url' ? ' *' : '' }}</label><input id="banner-{{ $field }}" name="{{ $field }}" type="{{ in_array($field, ['image_url','link_url']) ? 'url' : 'text' }}" value="{{ old($field, $item->$field ?? '') }}" maxlength="{{ in_array($field, ['image_url','link_url']) ? 2048 : 255 }}" @required($field !== 'link_url') @if($field==='image_url') data-banner-image @endif @if($field==='alt_text') data-banner-alt @endif></div>
    @endforeach
    <h2>Publishing</h2>
    <div class="nx-form-grid"><div><label for="banner-status">Status</label><select name="status" id="banner-status"><option value="inactive" @selected(old('status', $item->status ?? 'inactive')==='inactive')>Inactive</option><option value="active" @selected(old('status', $item->status ?? 'inactive')==='active')>Active</option></select></div><div><label for="banner-order">Display order</label><input type="number" name="sort_order" id="banner-order" min="0" max="100000" required value="{{ old('sort_order', $item->sort_order ?? 0) }}"></div>
    @foreach(['start_date'=>'Starts at (UTC)', 'end_date'=>'Ends at (UTC)'] as $field=>$label)
    @php($date=old($field, $item->$field ?? null))
    <div><label for="banner-{{ $field }}">{{ $label }}</label><input type="datetime-local" id="banner-{{ $field }}" name="{{ $field }}" value="{{ $date ? \Illuminate\Support\Carbon::parse($date)->format('Y-m-d\TH:i') : '' }}"></div>
    @endforeach</div>
    <div class="nx-dialog-footer"><a class="nx-button secondary" href="{{ route('management.index', 'banners') }}">Cancel</a><button class="nx-button" data-busy-submit><x-admin-icon name="check" /> Save banner</button></div>
</div>
<aside class="e24-banner-preview"><h2>Banner preview</h2><div class="e24-banner-stage"><img data-banner-preview alt="{{ old('alt_text', $item->alt_text ?? '') }}" hidden referrerpolicy="no-referrer"><p data-banner-placeholder>No image selected</p></div><p data-banner-error hidden role="status">Image could not be loaded.</p><dl><div><dt>Placement</dt><dd>Homepage carousel</dd></div><div><dt>Recommended dimensions</dt><dd>1600 x 600 px</dd></div></dl></aside>
</form>
@endsection
