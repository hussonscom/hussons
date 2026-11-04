<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#101b2b">
    <title>@yield('title', __('ui.dashboard')) · Hussons One</title>
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="workspace-shell">
@php
    $workspace = $currentOrganization ?? null;
    $branch = $currentBranch ?? null;
    $user = auth()->user();
    $membership = $currentMembership ?? null;
    $role = $membership?->role;
    $enabledModules = is_array($workspace?->modules)
        ? array_values(array_unique(array_map(static fn ($module) => strtolower(trim((string) $module)), $workspace->modules)))
        : [];
    $moduleEnabled = static function (string $module) use ($enabledModules): bool {
        if ($enabledModules === []) return true;
        $aliases = match ($module) {
            'restaurant' => ['restaurant', 'cafe', 'food'],
            'retail' => ['retail', 'electronics'],
            default => [$module],
        };
        return array_intersect($enabledModules, $aliases) !== [];
    };
    $navigation = [
        ['route' => 'dashboard', 'fallback' => '/app', 'icon' => 'dashboard', 'label' => __('ui.dashboard'), 'roles' => ['owner', 'admin', 'branch_manager', 'cashier', 'warehouse', 'pharmacist', 'kitchen', 'auditor', 'viewer'], 'module' => null],
        ['route' => 'catalog.index', 'fallback' => '/app/products', 'icon' => 'box', 'label' => __('ui.catalog'), 'roles' => ['owner', 'admin', 'branch_manager', 'warehouse', 'cashier', 'pharmacist', 'kitchen', 'auditor', 'viewer'], 'module' => null],
        ['route' => 'inventory.index', 'fallback' => '/app/inventory', 'icon' => 'warehouse', 'label' => __('ui.inventory'), 'roles' => ['owner', 'admin', 'branch_manager', 'warehouse'], 'module' => null],
        ['route' => 'sales.index', 'fallback' => '/app/sales', 'icon' => 'cart', 'label' => __('ui.sales'), 'roles' => ['owner', 'admin', 'branch_manager', 'cashier', 'pharmacist'], 'module' => null],
        ['route' => 'directory.index', 'fallback' => '/app/directory', 'icon' => 'users', 'label' => __('directory.title'), 'roles' => ['owner', 'admin', 'branch_manager', 'warehouse', 'cashier', 'pharmacist'], 'module' => null],
        ['route' => 'pharmacy.index', 'fallback' => '/app/pharmacy', 'icon' => 'pill', 'label' => __('ui.pharmacy_module'), 'roles' => ['owner', 'admin', 'branch_manager', 'pharmacist', 'auditor'], 'module' => 'pharmacy'],
        ['route' => 'restaurant.index', 'fallback' => '/app/restaurant', 'icon' => 'food', 'label' => __('ui.restaurant_module'), 'roles' => ['owner', 'admin', 'branch_manager', 'cashier', 'kitchen'], 'module' => 'restaurant'],
        ['route' => 'team.index', 'fallback' => '/app/team', 'icon' => 'users', 'label' => __('ui.team'), 'roles' => ['owner', 'admin'], 'module' => null],
    ];
@endphp
    <aside class="workspace-sidebar" data-sidebar>
        <div class="sidebar-brand">
            <a href="{{ Route::has('dashboard') ? route('dashboard') : url('/app') }}"><x-brand-mark :on-dark="true" /></a>
            <button class="sidebar-close" type="button" aria-label="Close" data-sidebar-close><x-icon name="close" /></button>
        </div>

        <div class="workspace-picker">
            <span>{{ __('ui.workspace') }}</span>
            <strong>{{ $workspace?->name ?? 'Hussons One' }}</strong>
            <small>{{ $branch?->name ?? __('ui.system') }}</small>
        </div>

        <nav class="workspace-nav">
            @foreach ($navigation as $item)
                @continue(! Route::has($item['route']))
                @continue($item['roles'] !== null && ! in_array($role, $item['roles'], true))
                @continue($item['module'] !== null && ! $moduleEnabled($item['module']))
                @php
                    $href = route($item['route']);
                    $active = request()->routeIs($item['route']) || request()->is(trim($item['fallback'], '/').'*');
                @endphp
                <a href="{{ $href }}" data-nav-route="{{ $item['route'] }}" @class(['is-active' => $active])>
                    <x-icon :name="$item['icon']" /><span>{{ $item['label'] }}</span>
                </a>
            @endforeach
        </nav>

        <div class="sidebar-bottom">
            @if ($user?->is_platform_admin && Route::has('platform.index'))
                <a href="{{ route('platform.index') }}"><x-icon name="shield" /><span>{{ __('admin.platform') }}</span></a>
            @endif
            <div class="user-chip">
                <span class="user-avatar">{{ mb_strtoupper(mb_substr($user?->name ?? 'U', 0, 1)) }}</span>
                <span><strong>{{ $user?->name ?? 'User' }}</strong><small>{{ $user?->email }}</small></span>
            </div>
            <form method="POST" action="{{ Route::has('logout') ? route('logout') : url('/logout') }}">
                @csrf
                <button type="submit"><x-icon name="logout" /><span>{{ __('ui.logout') }}</span></button>
            </form>
        </div>
    </aside>

    <div class="workspace-main">
        <header class="workspace-topbar">
            <div class="topbar-title">
                <button class="sidebar-open" type="button" aria-label="{{ __('ui.menu') }}" data-sidebar-open><x-icon name="menu" /></button>
                <div><small>{{ $branch?->name ?? __('ui.workspace') }}</small><h1>@yield('title', __('ui.dashboard'))</h1></div>
            </div>
            <div class="topbar-actions">
                <div class="locale-switch locale-switch--app">
                    @foreach (['az', 'ru', 'en'] as $locale)
                        <a href="{{ url('/locale/'.$locale) }}" @class(['is-active' => app()->getLocale() === $locale])>{{ strtoupper($locale) }}</a>
                    @endforeach
                </div>
            </div>
        </header>

        <main class="workspace-content">
            @if (session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
            @if (session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
            @if (session('error'))<div class="alert alert-error">{{ session('error') }}</div>@endif
            @if ($errors->any())
                <div class="alert alert-error"><strong>{{ $errors->first() }}</strong></div>
            @endif
            @yield('content')
        </main>
    </div>
    <div class="sidebar-scrim" data-sidebar-scrim hidden></div>
    @stack('scripts')
</body>
</html>
