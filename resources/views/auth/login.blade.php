@extends('layouts.auth')

@section('title', __('ui.login'))

@section('content')
<div class="auth-heading">
    <span class="auth-mark">H·1</span>
    <h1>{{ __('ui.login_title') }}</h1>
    <p>{{ __('ui.login_text') }}</p>
</div>

@if (session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
@if ($errors->any())<div class="alert alert-error">{{ $errors->first() }}</div>@endif

<form class="auth-form" method="POST" action="{{ Route::has('login.store') ? route('login.store') : url('/login') }}">
    @csrf
    <label class="field">
        <span>{{ __('ui.email') }}</span>
        <input type="email" name="email" value="{{ old('email') }}" autocomplete="email" inputmode="email" required autofocus placeholder="name@company.az">
    </label>
    <label class="field">
        <span>{{ __('ui.password') }}</span>
        <div class="password-field">
            <input type="password" name="password" autocomplete="current-password" required data-password-input>
            <button type="button" aria-label="Show password" data-password-toggle>◉</button>
        </div>
    </label>
    <div class="form-options">
        <label class="check-field"><input type="checkbox" name="remember" value="1"><span>{{ __('ui.remember') }}</span></label>
        @if (Route::has('password.request'))<a href="{{ route('password.request') }}">{{ __('ui.forgot') }}</a>@endif
    </div>
    <button class="btn btn-primary btn-block btn-large" type="submit">{{ __('ui.login_action') }} <x-icon name="arrow" /></button>
</form>

<p class="auth-switch">{{ __('ui.no_account') }} <a href="{{ Route::has('register') ? route('register') : url('/register') }}">{{ __('ui.create_workspace') }}</a></p>
@endsection
