<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#101b2b">
    <meta name="description" content="Hussons One — sales, inventory and business operations in one workspace.">
    <title>@yield('title', 'Hussons One')</title>
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="public-shell">
    <a class="skip-link" href="#main">{{ __('ui.skip') }}</a>
    <header class="site-header" data-site-header>
        <div class="site-container site-header__inner">
            <a href="{{ url('/') }}" aria-label="Hussons One">
                <x-brand-mark />
            </a>

            <nav class="site-nav" aria-label="Main navigation">
                <a href="#solutions">{{ __('ui.nav_solutions') }}</a>
                <a href="#platform">{{ __('ui.nav_platform') }}</a>
                <a href="#security">{{ __('ui.nav_security') }}</a>
            </nav>

            <div class="site-actions">
                <div class="locale-switch" aria-label="Language">
                    @foreach (['az', 'ru', 'en'] as $locale)
                        <a href="{{ url('/locale/'.$locale) }}" @class(['is-active' => app()->getLocale() === $locale])>{{ strtoupper($locale) }}</a>
                    @endforeach
                </div>
                @auth
                    <a class="btn btn-dark" href="{{ Route::has('dashboard') ? route('dashboard') : url('/app') }}">{{ __('ui.open_system') }}</a>
                @else
                    <a class="site-login" href="{{ Route::has('login') ? route('login') : url('/login') }}">{{ __('ui.login') }}</a>
                    <a class="btn btn-dark" href="{{ Route::has('register') ? route('register') : url('/register') }}">{{ __('ui.start') }}</a>
                @endauth
                <button class="menu-toggle" type="button" aria-label="{{ __('ui.menu') }}" aria-expanded="false" data-menu-toggle>
                    <x-icon name="menu" />
                </button>
            </div>
        </div>
        <div class="mobile-nav" data-mobile-nav hidden>
            <a href="#solutions">{{ __('ui.nav_solutions') }}</a>
            <a href="#platform">{{ __('ui.nav_platform') }}</a>
            <a href="#security">{{ __('ui.nav_security') }}</a>
            <a href="{{ Route::has('login') ? route('login') : url('/login') }}">{{ __('ui.login') }}</a>
        </div>
    </header>

    <main id="main">@yield('content')</main>
    @stack('scripts')
</body>
</html>
