@extends('layouts.workspace')
@section('title','Appearance')
@section('workspace')
<div class="nx-page-title">
    <div><h1>Appearance</h1><p>Choose how your workspace looks on this browser.</p></div>
</div>
<section class="nx-panel">
    <div class="nx-panel-heading"><h2>Display preferences</h2></div>
    <div class="nx-panel-pad">
        <label for="settings-theme">Color theme</label>
        <select id="settings-theme" data-theme-select>
            <option value="dark">Dark theme</option>
            <option value="light">Light theme</option>
        </select>
        <p class="nx-field-help">Saved on this browser. Applies across your admin workspace.</p>
        <label class="nx-checkbox"><input type="checkbox" data-compact-toggle> Compact sidebar</label>
        <p class="nx-field-help">Keep more room for your content on desktop.</p>
    </div>
</section>
@endsection
