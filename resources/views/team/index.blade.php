@extends('layouts.app')

@section('title', __('admin.team_title'))

@section('content')
<div class="page-head">
    <div><p class="text-slate-500">{{ __('admin.team_subtitle') }}</p></div>
</div>

@if (session('invitation_url'))
    <section class="mb-6 rounded-2xl border border-emerald-200 bg-emerald-50 p-5 text-emerald-950">
        <strong class="block">{{ __('invitations.share_once') }}</strong>
        <p class="mt-1 text-sm">{{ __('invitations.share_help') }}</p>
        <div class="mt-3 flex gap-2"><input class="min-w-0 flex-1 rounded-xl border border-emerald-200 bg-white px-3 py-2 font-mono text-xs" value="{{ session('invitation_url') }}" readonly onclick="this.select()"><button class="btn" type="button" onclick="navigator.clipboard.writeText(this.previousElementSibling.value)">{{ __('invitations.copy') }}</button></div>
    </section>
@endif

<div class="grid gap-6 xl:grid-cols-[340px_1fr]">
    <aside class="space-y-4">
        <section class="panel">
            <header class="panel-head"><h3>{{ __('admin.add_existing') }}</h3></header>
            <form class="panel-body space-y-4" method="POST" action="{{ route('team.store') }}">
                @csrf
                <label class="field"><span>{{ __('admin.email') }}</span><input type="email" name="email" value="{{ old('email') }}" autocomplete="email" required></label>
                <label class="field"><span>{{ __('admin.role') }}</span><select name="role" required>@foreach($assignableRoles as $role)<option value="{{ $role }}" @selected(old('role') === $role)>{{ str_replace('_', ' ', ucfirst($role)) }}</option>@endforeach</select></label>
                <label class="field"><span>{{ __('admin.branch') }}</span><select name="branch_id"><option value="">{{ __('admin.all_branches') }}</option>@foreach($branches as $branch)<option value="{{ $branch->id }}" @selected((string) old('branch_id') === (string) $branch->id)>{{ $branch->name }}</option>@endforeach</select></label>
                <button class="btn btn-primary btn-block" type="submit">{{ __('admin.add_member') }}</button>
            </form>
        </section>
        <section class="panel">
            <header class="panel-head"><h3>{{ __('invitations.invite_new') }}</h3></header>
            <form class="panel-body space-y-4" method="POST" action="{{ route('team.invitations.store') }}">
                @csrf
                <label class="field"><span>{{ __('admin.email') }}</span><input type="email" name="email" autocomplete="email" required></label>
                <label class="field"><span>{{ __('admin.role') }}</span><select name="role" required>@foreach($assignableRoles as $role)<option value="{{ $role }}">{{ str_replace('_', ' ', ucfirst($role)) }}</option>@endforeach</select></label>
                <label class="field"><span>{{ __('admin.branch') }}</span><select name="branch_id"><option value="">{{ __('admin.all_branches') }}</option>@foreach($branches as $branch)<option value="{{ $branch->id }}">{{ $branch->name }}</option>@endforeach</select></label>
                <button class="btn btn-primary btn-block" type="submit">{{ __('invitations.create_link') }}</button>
            </form>
        </section>
        <div class="rounded-2xl border border-blue-100 bg-blue-50 p-4 text-sm leading-6 text-blue-900">{{ __('invitations.expires_help') }}</div>
        @if($pendingInvitations->isNotEmpty())
            <section class="panel p-4">
                <strong class="text-sm">{{ __('invitations.pending') }}</strong>
                <div class="mt-3 space-y-3">@foreach($pendingInvitations as $invitation)<div class="rounded-xl border border-slate-100 p-3"><strong class="block text-sm">{{ $invitation->email }}</strong><small class="text-slate-500">{{ $invitation->role }} · {{ $invitation->expires_at->diffForHumans() }}</small></div>@endforeach</div>
            </section>
        @endif
    </aside>

    <section class="panel overflow-hidden">
        <div class="overflow-x-auto"><table class="data-table min-w-[850px]">
            <thead><tr><th>{{ __('admin.user') }}</th><th>{{ __('admin.role') }}</th><th>{{ __('admin.branch') }}</th><th>{{ __('admin.status') }}</th><th class="text-right">{{ __('admin.access') }}</th></tr></thead>
            <tbody>
            @foreach($memberships as $member)
                @php
                    $canEdit = $actorMembership->role === 'owner' || $member->role !== 'owner';
                @endphp
                <tr>
                    <td>
                        <form id="membership-{{ $member->id }}" method="POST" action="{{ route('team.update', $member) }}">@csrf @method('PATCH')</form>
                        <strong class="block text-slate-950">{{ $member->user->name }}</strong>
                        <small class="text-slate-400">{{ $member->user->email }}</small>
                    </td>
                    <td><select class="rounded-xl border border-slate-200 px-3 py-2 text-sm" name="role" form="membership-{{ $member->id }}" @disabled(!$canEdit)>@foreach($assignableRoles as $role)<option value="{{ $role }}" @selected($member->role === $role)>{{ str_replace('_', ' ', ucfirst($role)) }}</option>@endforeach @if(!in_array($member->role, $assignableRoles, true))<option value="{{ $member->role }}" selected>{{ str_replace('_', ' ', ucfirst($member->role)) }}</option>@endif</select></td>
                    <td><select class="rounded-xl border border-slate-200 px-3 py-2 text-sm" name="branch_id" form="membership-{{ $member->id }}" @disabled(!$canEdit)><option value="">{{ __('admin.all_branches') }}</option>@foreach($branches as $branch)<option value="{{ $branch->id }}" @selected($member->branch_id === $branch->id)>{{ $branch->name }}</option>@endforeach</select></td>
                    <td><select class="rounded-xl border border-slate-200 px-3 py-2 text-sm" name="is_active" form="membership-{{ $member->id }}" @disabled(!$canEdit)><option value="1" @selected($member->is_active)>{{ __('admin.active') }}</option><option value="0" @selected(!$member->is_active)>{{ __('admin.inactive') }}</option></select></td>
                    <td class="text-right"><button class="btn" type="submit" form="membership-{{ $member->id }}" @disabled(!$canEdit)>{{ __('admin.save') }}</button></td>
                </tr>
            @endforeach
            </tbody>
        </table></div>
    </section>
</div>
@endsection
