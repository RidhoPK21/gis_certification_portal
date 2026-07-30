@props(['scheme'])

@if ($scheme)
    <span class="{{ $scheme->badge_class }}" title="{{ $scheme->name }}">
        {{ $scheme->short_name }}
    </span>
@else
    <span class="badge badge-neutral">-</span>
@endif
