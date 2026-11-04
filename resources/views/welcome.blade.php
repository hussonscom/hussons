@extends('layouts.public')

@section('title', 'Hussons One — '.__('ui.brand_tagline'))

@section('content')
<section class="hero-section">
    <div class="hero-orbit hero-orbit--one"></div>
    <div class="hero-orbit hero-orbit--two"></div>
    <div class="site-container hero-grid">
        <div class="hero-copy">
            <span class="eyebrow"><span></span>{{ __('ui.hero_eyebrow') }}</span>
            <h1>{{ __('ui.hero_title') }}</h1>
            <p>{{ __('ui.hero_text') }}</p>
            <div class="hero-actions">
                <a class="btn btn-primary btn-large" href="{{ Route::has('register') ? route('register') : url('/register') }}">{{ __('ui.hero_primary') }} <x-icon name="arrow" /></a>
                <a class="text-link" href="#solutions">{{ __('ui.hero_secondary') }} <span>↓</span></a>
            </div>
            <div class="hero-metrics">
                <div><strong>∞</strong><span>{{ __('ui.metric_branches') }}</span></div>
                <div><strong>3</strong><span>{{ __('ui.metric_languages') }}</span></div>
                <div><strong>360°</strong><span>{{ __('ui.metric_control') }}</span></div>
            </div>
        </div>

        <div class="product-stage" aria-label="Hussons One workspace preview">
            <div class="stage-glow"></div>
            <div class="product-window">
                <div class="product-window__bar">
                    <div class="product-window__brand"><x-brand-mark size="sm" :wordmark="false" /><strong>One</strong></div>
                    <div class="product-window__search">⌕ &nbsp; {{ __('ui.search') }}</div>
                    <span class="product-window__avatar">H</span>
                </div>
                <div class="product-window__body">
                    <div class="mini-sidebar">
                        <span class="is-active"><x-icon name="dashboard" size="17" /></span>
                        <span><x-icon name="box" size="17" /></span>
                        <span><x-icon name="warehouse" size="17" /></span>
                        <span><x-icon name="cart" size="17" /></span>
                        <span><x-icon name="chart" size="17" /></span>
                    </div>
                    <div class="mini-dashboard">
                        <div class="mini-heading"><div><small>4 {{ __('ui.november') }}</small><strong>{{ __('ui.dashboard') }}</strong></div><button>+ {{ __('ui.sales_label') }}</button></div>
                        <div class="mini-kpis">
                            <article><span>₼</span><small>{{ __('ui.sales_label') }}</small><strong>12 840</strong><em>+18.2%</em></article>
                            <article><span>↗</span><small>{{ __('ui.orders') }}</small><strong>184</strong><em>+8.4%</em></article>
                            <article><span>◫</span><small>{{ __('ui.products') }}</small><strong>2 461</strong><em>38 {{ __('ui.low_stock') }}</em></article>
                        </div>
                        <div class="mini-grid">
                            <article class="mini-chart"><div><strong>{{ __('ui.sales_dynamics') }}</strong><small>{{ __('ui.last_7_days') }}</small></div><svg viewBox="0 0 330 115" preserveAspectRatio="none"><defs><linearGradient id="chartFill" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#407cff" stop-opacity=".28"/><stop offset="1" stop-color="#407cff" stop-opacity="0"/></linearGradient></defs><path d="M0 95 C35 92 42 71 76 78 S121 98 151 58 S205 72 235 38 S285 47 330 12 L330 115 L0 115Z" fill="url(#chartFill)"/><path d="M0 95 C35 92 42 71 76 78 S121 98 151 58 S205 72 235 38 S285 47 330 12" fill="none" stroke="#407cff" stroke-width="3" stroke-linecap="round"/><circle cx="235" cy="38" r="5" fill="#fff" stroke="#407cff" stroke-width="3"/></svg></article>
                            <article class="mini-alerts"><strong>{{ __('ui.needs_attention') }}</strong><span><i class="amber"></i><b>6</b> {{ __('ui.low_stock') }}</span><span><i class="red"></i><b>3</b> {{ __('ui.expiring') }}</span><span><i class="green"></i><b>12</b> {{ __('ui.completed') }}</span></article>
                        </div>
                    </div>
                </div>
            </div>
            <div class="floating-card floating-card--stock"><span class="float-icon">✓</span><div><small>{{ __('ui.imei_checked') }}</small><strong>3568 •••• 8124</strong></div></div>
            <div class="floating-card floating-card--expiry"><span class="float-icon float-icon--warm">!</span><div><small>{{ __('ui.expiry_warning') }}</small><strong>14 {{ __('ui.days_left') }}</strong></div></div>
        </div>
    </div>
</section>

<section id="solutions" class="systems-section">
    <div class="site-container">
        <div class="section-heading">
            <div><span class="section-kicker">01 · {{ __('ui.systems_kicker') }}</span><h2>{{ __('ui.systems_title') }}</h2></div>
            <p>{{ __('ui.systems_text') }}</p>
        </div>
        <div class="systems-grid">
            @php
                $systems = [
                    ['electronics', 'electronics_text', 'box', '01', 'cobalt'],
                    ['pharmacy', 'pharmacy_text', 'pill', '02', 'mint'],
                    ['food', 'food_text', 'food', '03', 'orange'],
                    ['universal', 'universal_text', 'warehouse', '04', 'violet'],
                ];
            @endphp
            @foreach ($systems as [$title, $text, $icon, $number, $tone])
                <article class="system-card system-card--{{ $tone }}">
                    <div class="system-card__top"><span class="system-icon"><x-icon :name="$icon" size="25" /></span><small>{{ $number }}</small></div>
                    <h3>{{ __('ui.'.$title) }}</h3>
                    <p>{{ __('ui.'.$text) }}</p>
                    <a href="{{ Route::has('register') ? route('register') : url('/register') }}">{{ __('ui.open_system') }} <x-icon name="arrow" size="17" /></a>
                </article>
            @endforeach
        </div>
    </div>
</section>

<section id="platform" class="platform-section">
    <div class="site-container platform-grid">
        <div class="platform-copy">
            <span class="section-kicker">02 · {{ __('ui.platform_kicker') }}</span>
            <h2>{{ __('ui.platform_title') }}</h2>
            <p>{{ __('ui.platform_text') }}</p>
            <div class="feature-list">
                <article><span>01</span><div><h3>{{ __('ui.feature_stock') }}</h3><p>{{ __('ui.feature_stock_text') }}</p></div></article>
                <article><span>02</span><div><h3>{{ __('ui.feature_roles') }}</h3><p>{{ __('ui.feature_roles_text') }}</p></div></article>
                <article><span>03</span><div><h3>{{ __('ui.feature_audit') }}</h3><p>{{ __('ui.feature_audit_text') }}</p></div></article>
            </div>
        </div>
        <div class="operations-visual">
            <div class="ops-head"><span>{{ __('ui.operations') }}</span><strong>{{ __('ui.today') }}</strong></div>
            <div class="ops-total"><small>{{ __('ui.total_turnover') }}</small><strong>₼ 48,290.00</strong><span>+12.6% ↗</span></div>
            <div class="ops-bars"><i style="--h:43%"></i><i style="--h:62%"></i><i style="--h:48%"></i><i style="--h:78%"></i><i style="--h:69%"></i><i style="--h:91%"></i><i style="--h:83%"></i></div>
            <div class="ops-days"><span>B.e</span><span>Ç.a</span><span>Ç</span><span>C.a</span><span>C</span><span>Ş</span><span>B</span></div>
            <div class="ops-foot"><div><span class="pulse-dot"></span><small>{{ __('ui.active_registers') }}</small><strong>7 / 8</strong></div><div><small>{{ __('ui.average_receipt') }}</small><strong>₼ 68.40</strong></div></div>
        </div>
    </div>
</section>

<section id="security" class="security-section">
    <div class="site-container security-card">
        <div class="security-mark"><x-icon name="shield" size="38" /></div>
        <div><span class="section-kicker">03 · {{ __('ui.security_kicker') }}</span><h2>{{ __('ui.security_title') }}</h2><p>{{ __('ui.security_text') }}</p></div>
        <div class="security-points"><span><x-icon name="check" /> {{ __('ui.tenant_isolation') }}</span><span><x-icon name="check" /> {{ __('ui.role_access') }}</span><span><x-icon name="check" /> {{ __('ui.audit_trail') }}</span></div>
    </div>
</section>

<section class="cta-section">
    <div class="site-container cta-card">
        <div><span class="eyebrow eyebrow--light">Hussons One</span><h2>{{ __('ui.cta_title') }}</h2><p>{{ __('ui.cta_text') }}</p></div>
        <a class="btn btn-light btn-large" href="{{ Route::has('register') ? route('register') : url('/register') }}">{{ __('ui.hero_primary') }} <x-icon name="arrow" /></a>
    </div>
</section>

<footer class="site-footer">
    <div class="site-container footer-main">
        <div class="footer-brand"><x-brand-mark :on-dark="true" /><p>{{ __('ui.brand_tagline') }}</p></div>
        <div><strong>{{ __('ui.footer_product') }}</strong><a href="#solutions">{{ __('ui.nav_solutions') }}</a><a href="#platform">{{ __('ui.nav_platform') }}</a><a href="#security">{{ __('ui.nav_security') }}</a></div>
        <div><strong>{{ __('ui.footer_company') }}</strong><a href="mailto:hello@hussons.com">hello@hussons.com</a><span>Baku · Azerbaijan</span></div>
    </div>
    <div class="site-container footer-bottom"><span>© {{ date('Y') }} Hussons Group. {{ __('ui.footer_rights') }}</span><span>Baku · Azerbaijan</span></div>
</footer>
@endsection
