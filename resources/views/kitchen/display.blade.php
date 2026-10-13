@extends('layouts.kitchen', ['view' => 'display'])

@section('title', 'Kitchen Display · LaraBai Baristro')

@section('content')
    <header class="k-head">
        <a class="brand" href="{{ route('navigator') }}"><span class="brand-mark">LB</span></a>
        <h1>Kitchen display</h1>
        @include('kitchen.partials.stats')
        <div class="k-tools">
            <span class="clock" data-clock></span>
            <button class="k-btn" type="button" data-drawer-open>Sold-out items</button>
            <button class="k-btn" type="button" data-sound aria-pressed="false">Sound off</button>
            <button class="k-btn" type="button" data-fullscreen>Full screen</button>
            <x-theme-toggle />
            <span class="who">{{ $staff['name'] }}</span>
            <form method="POST" action="{{ route('logout') }}" style="display:contents">
                @csrf
                <button class="k-btn" type="submit">Log out</button>
            </form>
        </div>
    </header>

    <div data-kitchen data-board-url="{{ route('kitchen.board') }}" style="display:contents">
        @include('kitchen.partials.columns')
        @include('kitchen.partials.recent')
    </div>

    {{-- Sold-out switches: each switch is a form, submitted when flipped --}}
    <div class="drawer-back" data-drawer-close {{ $drawerOpen ? "" : "hidden" }}></div>
    <aside class="drawer" data-drawer {{ $drawerOpen ? "" : "hidden" }} aria-label="Menu availability">
        <div class="drawer-head"><h2 style="font-size:1.1rem">Menu availability</h2><button class="k-btn" type="button" data-drawer-close>Done</button></div>
        <div class="drawer-body">
            @foreach ($categories as $category)
                <h3>{{ $category['name'] }}</h3>
                @foreach ($menu[$category['code']] ?? [] as $item)
                    <form method="POST" action="{{ route('kitchen.availability.update', $item['code']) }}" @class(['avail', 'off' => $item['sold_out']]) data-autosubmit>
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="available" value="0">
                        <span>{{ $item['name'] }}{{ $item['sold_out'] ? ' · sold out' : '' }}</span>
                        <span class="toggle"><input type="checkbox" name="available" value="1" @checked(! $item['sold_out']) aria-label="{{ $item['name'] }} available"><span></span></span>
                    </form>
                @endforeach
            @endforeach
        </div>
    </aside>
@endsection
