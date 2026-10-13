<aside class="side" id="side">
    <a class="brand" href="{{ route('navigator') }}"><x-brand-mark /><span class="brand-name">LaraBai Baristro</span></a>

    @foreach ($groups as $group)
        <nav class="side-group" aria-label="{{ $group['group'] }}">
            <h4>{{ $group['group'] }}</h4>
            @foreach ($group['links'] as $link)
                <a class="nav{{ $link['id'] === $active ? ' on' : '' }}" href="{{ route($link['route']) }}">
                    <span>{{ $link['label'] }}</span>
                    @if (! empty($link['badge']) && ! empty($badges[$link['badge']]))
                        <span class="count">{{ $badges[$link['badge']] }}</span>
                    @endif
                </a>
            @endforeach
        </nav>
    @endforeach

    <div class="side-user">
        <span><b>{{ $user['name'] }}</b><small>{{ $role->label() }}</small></span>
        <div class="side-buttons">
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit">Log out</button>
            </form>
            <x-theme-toggle />
        </div>
    </div>
</aside>
