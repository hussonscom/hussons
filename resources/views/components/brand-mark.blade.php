@props(['size' => 'md', 'onDark' => false, 'wordmark' => true])

@php
    $sizes = [
        'sm' => 'h-8',
        'md' => 'h-10',
        'lg' => 'h-12',
    ];
@endphp

<span {{ $attributes->class(['brand-mark', $onDark ? 'brand-mark--dark' : '']) }}>
    <svg class="{{ $sizes[$size] ?? $sizes['md'] }} w-auto" viewBox="0 0 48 48" aria-hidden="true">
        <rect width="48" height="48" rx="15" fill="currentColor"/>
        <path d="M13 13.5v21M13 24h19M32 13.5v21" fill="none" stroke="var(--brand-mark-stroke, #fff)" stroke-width="4.2" stroke-linecap="round"/>
        <circle cx="36.5" cy="11.5" r="4.5" fill="var(--brand-mark-dot, #b6f36b)" stroke="var(--brand-mark-stroke, #fff)" stroke-width="2"/>
    </svg>
    @if ($wordmark)
        <span class="brand-wordmark">
            <strong>Hussons</strong><span>One</span>
        </span>
    @endif
</span>
