@extends('layouts.public', ['module' => 'index'])

@section('title', 'System Navigator · LaraBai Baristro')

@section('content')
    <header class="topbar">
        <div class="container">
            <a class="brand" href="{{ route('navigator') }}">
                <span class="brand-mark">LB</span>
                <span class="brand-name">LaraBai Baristro</span>
            </a>
            <div class="topbar-right">
                <span class="tag">Demo navigator</span>
                <x-theme-toggle />
            </div>
        </div>
    </header>

    <main class="container">
        <section class="hero">
            <div>
                <div class="eyebrow">Case study · Tarlac City</div>
                <h1>LaraBai Baristro ordering system</h1>
                <p class="lead">Every screen of the QR ordering, kitchen and inventory system in one place. Use it to run the demo; customers never see this page.</p>
                <div class="live" aria-live="polite">
                    @foreach ($live as $item)
                        <span @class(['live-item', 'attn' => $item['n'] && $item['attn'], 'ok' => $item['n'] && ! $item['attn']])><span class="dot" aria-hidden="true"></span><b>{{ $item['n'] }}</b> {{ $item['label'] }}</span>
                    @endforeach
                </div>
            </div>

            <aside class="walk" aria-labelledby="walkTitle">
                <h2 id="walkTitle">Suggested walkthrough</h2>
                <p>Follow one order from the table to the sales report. Open each step in a new tab.</p>
                <ol>
                    @foreach ($walk as $step)
                        <li><a href="{{ $step['href'] }}" target="_blank" rel="noopener">
                            <b>{{ $step['title'] }}</b><span class="go" aria-hidden="true">↗</span>
                            <small>{{ $step['sub'] }}@isset($step['login']) · <code>{{ $step['login'] }}</code>@endisset</small>
                        </a></li>
                    @endforeach
                </ol>
            </aside>
        </section>

        <section class="section" aria-labelledby="tablesTitle">
            <div class="section-head">
                <div>
                    <h2 id="tablesTitle">Scan a table</h2>
                    <p>Every table has a QR standee in the middle. Opening a table is the same as a customer scanning it; take-out and delivery start from the counter QR.</p>
                </div>
            </div>
            <div class="tables">
                @foreach ($spots as $spot)
                    <a @class(['spot', 'busy' => $spot['busy'], 'rider' => $spot['rider']]) href="{{ $spot['href'] }}" aria-label="{{ $spot['aria'] }}">
                        <svg class="fp" viewBox="-14 -12 148 124" aria-hidden="true">{!! $spot['svg'] !!}</svg>
                        <span class="spot-label"><b>{{ $spot['title'] }}</b><small>{{ $spot['sub'] }}</small></span>
                        <span class="state">{{ $spot['state'] }}</span>
                    </a>
                @endforeach
            </div>
        </section>

        <section class="section" aria-labelledby="rolesTitle">
            <div class="section-head">
                <div>
                    <h2 id="rolesTitle">Screens by role</h2>
                    <p>The demo accounts are shown on each card. Log in with one at the <a class="link-btn" href="{{ route('login') }}">staff login</a> to see the sidebar change to that role.</p>
                </div>
            </div>
            <div class="roles">
                @foreach ($roles as $role)
                    <article class="role">
                        <div class="role-head">
                            <span class="role-icon">@include('public.partials.role-icon', ['icon' => $role['icon']])</span>
                            <h3>{{ $role['title'] }}</h3>
                            @isset($role['badge'])<span class="chip {{ $role['badge'][0] }}">{{ $role['badge'][1] }}</span>@endisset
                            <p>{{ $role['who'] }}</p>
                        </div>
                        @if ($role['login'])
                            <div class="login-hint"><span>Demo login</span><code>{{ $role['login'] }}</code></div>
                        @endif
                        <ul>
                            @foreach ($role['screens'] as $screen)
                                @if ($screen['flow'])
                                    <li><div><span class="name">{{ $screen['name'] }}</span><span class="flow">{{ $screen['flow'] }}</span></div></li>
                                @else
                                    <li><a href="{{ $screen['href'] }}"><span class="name">{{ $screen['name'] }}</span><span class="path">{{ $screen['path'] }}</span><span class="go" aria-hidden="true">→</span></a></li>
                                @endif
                            @endforeach
                        </ul>
                    </article>
                @endforeach
            </div>
        </section>
    </main>

    <footer>
        <div class="container">
            <span>LaraBai Baristro is a fictional business for a case study. Orders and payments here are sample data; nothing is saved.</span>
            <span class="reset">
                <a href="{{ route('navigator.components.staff') }}">Shared components</a> ·
                <a class="btn btn-ghost" href="{{ route('admin.demo-reset.show') }}">Reset demo data</a>
            </span>
        </div>
    </footer>
@endsection
