<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#101b2b">
    <title>@yield('title') · Hussons One</title>
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="auth-shell">
    <div class="auth-aside">
        <a href="{{ url('/') }}" aria-label="Hussons One"><x-brand-mark size="lg" :on-dark="true" /></a>
        <div class="auth-aside__story">
            <span class="eyebrow eyebrow--light">Hussons One</span>
            <p>{{ __('ui.hero_title') }}</p>
            <div class="auth-proof">
                <span><x-icon name="check" /> {{ __('ui.feature_stock') }}</span>
                <span><x-icon name="check" /> {{ __('ui.feature_roles') }}</span>
                <span><x-icon name="check" /> {{ __('ui.feature_audit') }}</span>
            </div>
        </div>
        <small>© {{ date('Y') }} Hussons Group</small>
    </div>
    <main class="auth-main">
        <div class="auth-topline">
            <a class="auth-back" href="{{ url('/') }}"><span>←</span> {{ __('ui.back_home') }}</a>
            <div class="locale-switch">
                @foreach (['az', 'ru', 'en'] as $locale)
                    <a href="{{ url('/locale/'.$locale) }}" @class(['is-active' => app()->getLocale() === $locale])>{{ strtoupper($locale) }}</a>
                @endforeach
            </div>
        </div>
        <div class="auth-card">
            @yield('content')
        </div>
    </main>
</body>
</html>
