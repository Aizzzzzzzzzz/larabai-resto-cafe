<ol {{ $attributes->class('timeline') }}>
    @foreach ($steps as $step)
        <li class="{{ $step['class'] }}">
            <span class="tl-dot">
                @if ($step['class'] === 'done')
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m5 12 5 5 9-10"/></svg>
                @endif
            </span>
            <span><b>{{ $step['label'] }}</b>@if ($step['sub'])<small>{{ $step['sub'] }}</small>@endif</span>
            <time>{{ $step['time'] }}</time>
        </li>
    @endforeach
</ol>
