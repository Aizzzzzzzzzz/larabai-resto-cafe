@props(['title', 'text' => null, 'icon' => 'cup'])

{{-- Customer-screen empty state (mockup .empty). icon: cup | receipt. Slot: the action button. --}}
<div {{ $attributes->class('empty') }}>
    @if ($icon === 'receipt')
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="5" y="3" width="14" height="18" rx="2"/><path d="M9 8h6M9 12h6M9 16h3"/></svg>
    @else
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 9h12v5a5 5 0 0 1-5 5h-2a5 5 0 0 1-5-5z"/><path d="M17 10h1.5a2.5 2.5 0 0 1 0 5H17"/></svg>
    @endif
    <h2>{{ $title }}</h2>
    @if ($text)
        <p class="muted">{{ $text }}</p>
    @endif
    {{ $slot }}
</div>
