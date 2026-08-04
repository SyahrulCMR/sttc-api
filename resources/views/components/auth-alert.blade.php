{{-- resources/views/components/auth-alert.blade.php --}}
@props(['type' => 'error'])

@php
    $icon = match($type) {
        'success' => '✓',
        'warning' => '⚠',
        default => '⨯',
    };
@endphp

<div class="auth-alert auth-alert--{{ $type }}" role="alert">
    <span aria-hidden="true">{{ $icon }}</span>
    <span>{{ $slot }}</span>
</div>
