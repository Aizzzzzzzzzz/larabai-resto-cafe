@props(['title', 'back', 'backLabel' => 'Back'])

{{-- Customer screen top bar: back arrow, title, and the scanned QR (e.g. "TABLE 3 · DINE-IN"). --}}
<header class="c-bar">
    <a class="c-back" href="{{ $back }}" aria-label="{{ $backLabel }}">←</a>
    <h1>{{ $title }}</h1>
    <span class="session-pill">{{ $sessionLabel }}</span>
</header>
