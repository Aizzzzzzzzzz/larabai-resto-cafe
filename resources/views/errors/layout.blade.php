{{--
    Friendly error page shared by 403, 404, 419, 429, 500 and 503.
    Deliberately standalone: it never touches the session or the database, so it still renders when they are
    the thing that broke. Child views set $code, $title, $text (sections) and may add buttons in `actions`.
--}}
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="robots" content="noindex">
    <title>@yield('title') · {{ config('cafe.name') }}</title>
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,500;12..96,700;12..96,800&family=Figtree:wght@400;500;600&family=JetBrains+Mono:wght@400;500&display=swap">
    <script>
        (function () {
            var saved = null;
            try { saved = localStorage.getItem('bbTheme'); } catch (e) {}
            var dark = window.matchMedia && matchMedia('(prefers-color-scheme: dark)').matches;
            document.documentElement.dataset.theme = saved || (dark ? 'dark' : 'light');
        })();
    </script>
    @vite(['resources/css/app.css'])
</head>
<body data-module="error" class="v-error-page">
    <main class="error-page">
        <a class="brand" href="{{ url('/') }}"><span class="brand-mark">LB</span><span class="brand-name">{{ config('cafe.name') }}</span></a>
        <p class="error-code">@yield('code')</p>
        <h1>@yield('title')</h1>
        <p class="error-text">@yield('text')</p>
        <div class="error-actions">
            @yield('actions')
            <a class="btn btn-ghost" href="{{ url('/') }}">Back to the café’s homepage</a>
        </div>
    </main>
</body>
</html>
