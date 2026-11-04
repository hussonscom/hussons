@extends('layouts.auth')

@section('title', __('invitations.title'))
@section('content')
<div class="auth-heading">
    <span class="auth-mark">H·1</span>
    <h1>{{ __('invitations.heading') }}</h1>
    <p>{{ __('invitations.subheading', ['company' => $invitation->organization->name]) }}</p>
</div>

@if ($errors->any())<div class="alert alert-error">{{ $errors->first() }}</div>@endif

<form method="POST" action="{{ url('/invitation/'.$token) }}" class="auth-form">
    @csrf
    <label class="field">
        <span>{{ __('admin.email') }}</span>
        <input type="email" value="{{ $invitation->email }}" disabled>
    </label>
    <label class="field">
        <span>{{ __('invitations.name') }}</span>
        <input type="text" name="name" value="{{ old('name') }}" autocomplete="name" maxlength="120" required autofocus>
    </label>
    <label class="field">
        <span>{{ __('invitations.password') }}</span>
        <input type="password" name="password" autocomplete="new-password" required>
    </label>
    <label class="field">
        <span>{{ __('invitations.password_confirmation') }}</span>
        <input type="password" name="password_confirmation" autocomplete="new-password" required>
    </label>
    <label class="field">
        <span>{{ __('invitations.language') }}</span>
        <select name="locale" required>
            <option value="az" @selected(old('locale', 'az') === 'az')>Azərbaycan</option>
            <option value="ru" @selected(old('locale') === 'ru')>Русский</option>
            <option value="en" @selected(old('locale') === 'en')>English</option>
        </select>
    </label>
    <div class="rounded-xl border border-slate-200 bg-slate-50 p-3 text-sm text-slate-600">
        {{ __('invitations.access_summary', ['role' => str_replace('_', ' ', $invitation->role), 'branch' => $invitation->branch?->name ?? __('admin.all_branches')]) }}
    </div>
    <button class="btn btn-primary btn-block btn-large" type="submit">{{ __('invitations.accept') }}</button>
</form>
@endsection
