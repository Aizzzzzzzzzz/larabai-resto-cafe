@extends('layouts.guest')

@section('title', 'Staff login · LaraBai Baristro')

@section('content')
    <div class="login">
        <aside class="board" aria-label="Today at the café">
            <div class="board-top">
                <a class="brand" href="{{ config('cafe.demo_mode') ? route('navigator') : route('home') }}"><span class="brand-mark">LB</span><span class="brand-name">{{ config('cafe.name') }}</span></a>
                <x-theme-toggle />
            </div>

            <div class="board-main">
                <div>
                    <div class="eyebrow">{{ $today }}</div>
                    <h1>{{ $greeting }}<br><span>Good shift ahead.</span></h1>
                    <p class="board-lead">Here’s what is waiting right now. Log in to open your screen.</p>
                </div>
                <div class="shift" aria-live="polite">
                    @foreach ($shift as $tile)
                        <div @class(['hot' => $tile['n']])><strong>{{ $tile['n'] }}</strong><span>{{ $tile['label'] }}</span></div>
                    @endforeach
                </div>
            </div>

            <div class="board-foot"><span>Staff only</span><span>Every login is recorded in the audit log</span></div>
        </aside>

        <main class="side-form">
            <div class="login-card">
                @if ($nextName)
                    <div class="context">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>
                        <span>Log in to open <b>{{ $nextName }}</b>.</span>
                    </div>
                @endif

                <h2>Log in</h2>
                <p>Use your staff username and password.</p>

                @if ($me)
                    {{-- Already logged in: carry on, or switch to someone else --}}
                    <div class="signed-in">
                        <div class="who">
                            <span class="avatar" style="width:40px;height:40px;border-radius:50%;background:var(--accent);color:var(--on-accent);display:grid;place-items:center;font:700 .85rem var(--display)">{{ collect(explode(' ', $me['name']))->map(fn ($p) => mb_substr($p, 0, 1))->take(2)->join('') }}</span>
                            <span><b>You’re logged in as {{ $me['name'] }}</b><br><small class="muted">{{ $meRole->label() }}</small></span>
                        </div>
                        <div class="row">
                            <a class="btn btn-primary" href="{{ $meGoUrl }}">Continue to {{ $meGoName ?? 'your screen' }}</a>
                            <form method="POST" action="{{ route('logout') }}" style="display:contents">
                                @csrf
                                <button class="btn btn-ghost" type="submit">Switch user</button>
                            </form>
                        </div>
                    </div>
                @else
                    <form method="POST" action="{{ route('login.store') }}" novalidate style="display:grid;gap:16px" data-login>
                        @csrf
                        @if ($next)<input type="hidden" name="next" value="{{ $next }}">@endif

                        @if ($errors->any())
                            <div class="err" role="alert">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v6M12 16.5v.5"/></svg>
                                <span>{{ $errors->first() }}</span>
                            </div>
                        @endif

                        <div class="field">
                            <label for="username">Username</label>
                            <input class="input" id="username" name="username" value="{{ $prefill }}" autocomplete="username" autocapitalize="none" spellcheck="false" required @if ($errors->has('username') || $errors->has('credentials')) aria-invalid="true" @endif @unless ($prefill) autofocus @endunless>
                        </div>
                        <div class="field">
                            <label for="password">Password</label>
                            <div class="pw">
                                <input class="input" id="password" name="password" type="password" autocomplete="current-password" required @if ($errors->has('password') || $errors->has('credentials')) aria-invalid="true" @endif @if ($prefill) autofocus @endif>
                                <button type="button" aria-controls="password" aria-pressed="false" data-show-password>Show</button>
                            </div>
                        </div>
                        <button class="btn btn-primary submit" type="submit">Log in</button>
                    </form>

                    @if ($accounts->isNotEmpty())
                        <section class="demo" aria-labelledby="demoTitle">
                            <div class="demo-head"><h3 id="demoTitle">Demo accounts</h3><span>Pick one to fill the form</span></div>
                            <div class="accts">
                                @foreach ($accounts as $account)
                                    <button type="button" class="acct" aria-pressed="false" data-username="{{ $account['username'] }}" data-password="{{ $account['password'] }}">
                                        <span class="avatar">{{ $account['initials'] }}</span>
                                        <b>{{ $account['full_name'] }}</b>
                                        <small>{{ $account['role_label'] }} · opens {{ $account['opens'] }}</small>
                                        <code>{{ $account['username'] }}</code>
                                    </button>
                                @endforeach
                            </div>
                        </section>
                    @endif
                @endif
            </div>
        </main>
    </div>
@endsection
