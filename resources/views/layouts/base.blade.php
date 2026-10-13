{{--
    Base page for every screen.
    Expects: $module (body data-module, e.g. 'cashier'), $view (e.g. 'pos'), optional $bodyClass.
    The body gets the mockup's scoping classes: $bodyClass + "v-<module>-<view>", so mockup.css applies unchanged.
--}}
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', config('cafe.name'))</title>
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    @hasSection('description')
        <meta name="description" content="@yield('description')">
    @endif
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,500;12..96,700;12..96,800&family=Figtree:wght@400;500;600&family=JetBrains+Mono:wght@400;500&display=swap">
    {{-- Set the theme before first paint (resources/js/core/theme.js takes over after load) --}}
    <script>
        (function () {
            var kitchen = @js($module === 'kitchen'), saved = null;
            try { saved = localStorage.getItem(kitchen ? 'bbThemeKitchen' : 'bbTheme'); } catch (e) {}
            var dark = window.matchMedia && matchMedia('(prefers-color-scheme: dark)').matches;
            document.documentElement.dataset.theme = saved || (kitchen || dark ? 'dark' : 'light');
        })();
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body data-module="{{ $module }}" class="{{ trim(($bodyClass ?? '').' v-'.$module.'-'.$view) }}">
    @yield('body')

    @stack('modals')

    {{-- Flash message from the last form submit, shown as the mockup's toast --}}
    <div class="toast-pop" role="status" @unless (session('status')) hidden @endunless>{{ session('status') }}</div>

    @stack('scripts')
</body>
</html>
