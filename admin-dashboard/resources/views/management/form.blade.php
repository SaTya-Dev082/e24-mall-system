@extends('layouts.workspace')
@section('title',$def['title'])
@section('workspace')
<div class="nx-page-title"><div><a class="nx-back-link" href="{{ route('management.index',$resource) }}">← {{ $def['title'] }}</a><h1>{{ $item ? 'Edit' : 'Add' }} {{ strtolower($def['title']) }}</h1></div></div>
<section class="nx-panel nx-form-panel">@if($resource==='banners')<div class="nx-flash"><p>Add a headline, description and button text to display copy beside the image. Leave these blank for an image-only banner. Recommended size: 1600 × 600 px. Enter an HTTPS image URL and an optional destination URL. Dates use UTC. Only active banners within their schedule appear; lower sort order appears first.</p>@if($item)<img src="{{ $item->image_url }}" alt="{{ $item->alt_text }}" style="width:100%;max-height:240px;object-fit:contain">@endif</div>@endif
@if($resource==='reviews')<div class="nx-flash"><p>Product #{{ $item->product_id }} · Customer #{{ $item->user_id }} · Rating {{ $item->rating }}/5<br>{{ $item->comment }}</p></div>@endif<form method="POST" action="{{ $item ? route('management.update',[$resource,$item->id]) : route('management.store',$resource) }}">@csrf @if($item) @method('PUT') @endif<div class="nx-form-grid">
@foreach($def['fields'] as $field=>$rules)
@php($value=old($field,$item->$field??($field==='status'?'active':($field==='sort_order'||$field==='min_order_amount'?0:''))))
<div class="{{ in_array($field,['description']) ? 'nx-full-width':'' }}"><label for="field-{{ $field }}">{{ ucfirst(str_replace('_',' ',$field)) }} @if(str_contains($rules,'required'))<span aria-hidden="true">*</span>@endif</label>
@if(isset($options[$field]))<select id="field-{{ $field }}" name="{{ $field }}" @required(str_contains($rules,'required'))><option value="">{{ $field==='parent_id'?'Root category':'Choose an option' }}</option>@foreach($options[$field] as $key=>$label)<option value="{{ $key }}" @selected((string)$value===(string)$key)>{{ $label }}</option>@endforeach</select>
@elseif(preg_match('/(?:^|\|)in:([^|]+)/',$rules,$matches))<select id="field-{{ $field }}" name="{{ $field }}">@foreach(explode(',',$matches[1]) as $choice)<option value="{{ $choice }}" @selected($value===$choice)>{{ ucfirst(str_replace('_',' ',$choice)) }}</option>@endforeach</select>
@elseif($field==='description')<textarea id="field-{{ $field }}" name="{{ $field }}" rows="4" maxlength="5000">{{ $value }}</textarea>
@elseif(str_contains($rules,'|date'))<input id="field-{{ $field }}" name="{{ $field }}" type="datetime-local" value="{{ $value ? \Illuminate\Support\Carbon::parse($value)->format('Y-m-d\TH:i') : '' }}"><p class="nx-field-help">UTC date and time.</p>
@else<input id="field-{{ $field }}" name="{{ $field }}" type="{{ str_contains($rules,'numeric')||str_contains($rules,'integer')?'number':'text' }}" @if(str_contains($rules,'numeric')) step="0.01" min="0" @elseif(str_contains($rules,'integer')) step="1" min="0" @endif value="{{ $value }}" @required(str_contains($rules,'required')) @if(preg_match('/max:(\d+)/',$rules,$max)&&!str_contains($rules,'numeric')&&!str_contains($rules,'integer')) maxlength="{{ $max[1] }}" @endif>
@endif
@if($field==='slug')<p class="nx-field-help">Leave blank to generate a unique URL identifier.</p>@endif</div>
@endforeach
</div><div class="nx-dialog-footer"><a class="nx-button secondary" href="{{ route('management.index',$resource) }}">Cancel</a><button class="nx-button" data-busy-submit>Save changes</button></div></form></section>
@endsection
