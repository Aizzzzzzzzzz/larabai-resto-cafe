{{--
    Staff screens (cashier, kitchen-admin views, inventory, admin): dark sidebar + work area.
    Expects: $module, $view, $active (sidebar link id). Optional $badges for sidebar counts.
    Sections: title, heading, sub, actions, content. A screen with its own header can override 'work-head'.
--}}
@extends('layouts.base', ['bodyClass' => 'm-staff staff'])

@section('body')
    <div class="shell">
        <x-sidebar :active="$active ?? ''" :badges="$badges ?? []" />
        <div class="work">
            @section('work-head')
                <header class="work-head">
                    <div>
                        <h1>@yield('heading')</h1>
                        @hasSection('sub')<div class="sub">@yield('sub')</div>@endif
                    </div>
                    @hasSection('actions')
                        <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">@yield('actions')</div>
                    @endif
                </header>
            @show
            <div class="work-body">
                @yield('content')
            </div>
        </div>
    </div>
@endsection
