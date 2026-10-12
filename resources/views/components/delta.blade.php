@props(['now', 'before'])

{{-- "▲ 12% vs this time last week" under a stat card (mockup delta()) --}}
@if ($before)
    @if (! $now)
        <span class="hint">No paid orders yet today</span>
    @else
        @php($pct = (int) round(($now - $before) / $before * 100))
        <span class="delta {{ $pct >= 0 ? 'up' : 'down' }}">{{ $pct >= 0 ? '▲' : '▼' }} {{ abs($pct) }}% vs this time last week</span>
    @endif
@endif
