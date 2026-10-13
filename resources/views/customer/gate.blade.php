@extends('layouts.customer', ['view' => 'menu'])

@section('title', 'Menu · LaraBai Baristro')

{{-- Opened without scanning a table or counter QR --}}
@section('content')
    <div class="gate">
        <div class="gate-card">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 7V4a1 1 0 0 1 1-1h3M17 3h3a1 1 0 0 1 1 1v3M21 17v3a1 1 0 0 1-1 1h-3M7 21H4a1 1 0 0 1-1-1v-3"/><rect x="7" y="7" width="4" height="4"/><rect x="13" y="7" width="4" height="4"/><rect x="7" y="13" width="4" height="4"/><path d="M13 13h4v4"/></svg>
            <h1>Scan the QR code on your table</h1>
            <p class="muted">Each table has its own QR code so we know where to bring your order. For take-out, scan the QR code at the counter.</p>
            @if (config('cafe.demo_mode'))
                <a class="link-btn" href="{{ route('navigator') }}">Demo: open the system navigator to “scan” a table</a>
            @endif
        </div>
    </div>
@endsection
