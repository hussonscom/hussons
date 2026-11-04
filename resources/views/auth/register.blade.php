@extends('layouts.auth')

@section('title', __('ui.create_workspace'))

@section('content')
<div class="auth-heading">
    <span class="auth-mark">H·1</span>
    <h1>{{ __('ui.register_title') }}</h1>
    <p>{{ __('ui.register_text') }}</p>
</div>

@if ($errors->any())<div class="alert alert-error">{{ $errors->first() }}</div>@endif

<form class="auth-form" method="POST" action="{{ Route::has('register.store') ? route('register.store') : url('/register') }}">
    @csrf
    <div class="field-grid">
        <label class="field"><span>{{ __('ui.name') }}</span><input type="text" name="name" value="{{ old('name') }}" autocomplete="name" required autofocus></label>
        <label class="field"><span>{{ __('ui.company') }}</span><input type="text" name="organization_name" value="{{ old('organization_name') }}" autocomplete="organization" required></label>
    </div>
    <fieldset class="module-choices">
        <legend>{{ __('ui.business_type') }}</legend>
        <div class="module-choice-grid">
            @foreach ([
                'electronics' => ['box', __('ui.retail_option')],
                'pharmacy' => ['pill', __('ui.pharmacy_option')],
                'restaurant' => ['food', __('ui.food_option')],
                'retail' => ['warehouse', __('ui.universal_option')],
            ] as $value => [$icon, $label])
                <label class="module-choice">
                    <input type="checkbox" name="business_types[]" value="{{ $value }}" @checked(in_array($value, old('business_types', ['retail']), true))>
                    <span><x-icon :name="$icon" size="18" />{{ $label }}</span>
                </label>
            @endforeach
        </div>
    </fieldset>
    <label class="field"><span>{{ __('ui.email') }}</span><input type="email" name="email" value="{{ old('email') }}" autocomplete="email" inputmode="email" required placeholder="name@company.az"></label>
    <div class="field-grid">
        <label class="field"><span>{{ __('ui.password') }}</span><input type="password" name="password" autocomplete="new-password" minlength="10" required><small>{{ __('ui.password_hint') }}</small></label>
        <label class="field"><span>{{ __('ui.confirm_password') }}</span><input type="password" name="password_confirmation" autocomplete="new-password" minlength="10" required></label>
    </div>
    <input type="hidden" name="locale" value="{{ app()->getLocale() }}">
    <label class="check-field check-field--terms"><input type="checkbox" name="terms" value="1" required><span>{{ __('ui.agree') }}</span></label>
    <button class="btn btn-primary btn-block btn-large" type="submit">{{ __('ui.register_action') }} <x-icon name="arrow" /></button>
</form>

<p class="auth-switch">{{ __('ui.have_account') }} <a href="{{ Route::has('login') ? route('login') : url('/login') }}">{{ __('ui.login') }}</a></p>
@endsection
