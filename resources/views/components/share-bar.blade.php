@props(['parts'])

{{--
    100% share bar with a labelled legend (mockup BBChart.share).
    $parts: [['label' => 'GCash', 'value' => 1200, 'color' => 'var(--pay-gcash)'], …]
--}}
@php
    $total = array_sum(array_column($parts, 'value')) ?: 1;
    $pct = fn ($v) => round($v / $total * 100);
@endphp
<div>
    <div class="share-bar" role="img" aria-label="{{ collect($parts)->map(fn ($p) => $p['label'].' '.$pct($p['value']).'%')->join(', ') }}">
        @foreach ($parts as $p)
            @if ($p['value'] > 0)
                <i style="flex:{{ $p['value'] }};background:{{ $p['color'] }}" title="{{ $p['label'] }}: {{ peso($p['value'], false) }}"></i>
            @endif
        @endforeach
    </div>
    <div class="share-legend">
        @foreach ($parts as $p)
            <span><i style="background:{{ $p['color'] }}"></i>{{ $p['label'] }} <b>{{ $pct($p['value']) }}%</b> <small>{{ peso($p['value'], false) }}</small></span>
        @endforeach
    </div>
</div>
