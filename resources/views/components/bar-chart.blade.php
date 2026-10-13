<div {{ $attributes->class('chart') }}>
    <svg viewBox="0 0 {{ \App\View\Components\BarChart::WIDTH }} {{ $height }}" width="100%" role="img" aria-label="{{ $axisLabel }}">
        @foreach ($ticks as $tick)
            <line x1="56" x2="{{ \App\View\Components\BarChart::WIDTH - 8 }}" y1="{{ $tick['y'] }}" y2="{{ $tick['y'] }}" class="grid"/>
            <text x="48" y="{{ $tick['y'] + 4 }}" text-anchor="end" class="axis">{{ $tick['text'] }}</text>
        @endforeach
        @foreach ($bars as $bar)
            <g @class(['bar', 'hl' => $bar['hl']])>
                <title>{{ $bar['tip'] }}</title>
                <rect x="{{ $bar['hit']['x'] }}" y="{{ $bar['hit']['y'] }}" width="{{ $bar['hit']['w'] }}" height="{{ $bar['hit']['h'] }}" class="hit"/>
                @if ($bar['path'])<path d="{{ $bar['path'] }}"/>@endif
            </g>
            @if ($bar['showLabel'])
                <text x="{{ $bar['labelX'] }}" y="{{ $height - 8 }}" text-anchor="middle" class="axis">{{ $bar['label'] }}</text>
            @endif
        @endforeach
        <line x1="56" x2="{{ \App\View\Components\BarChart::WIDTH - 8 }}" y1="{{ $base }}" y2="{{ $base }}" class="base"/>
    </svg>
</div>
