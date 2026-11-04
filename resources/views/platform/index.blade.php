@extends('layouts.app')

@section('title', __('admin.platform_title'))

@section('content')
<div class="page-head"><div><p class="text-slate-500">{{ __('admin.platform_subtitle') }}</p></div><span class="badge bg-blue-100 text-blue-700">{{ __('admin.read_only') }}</span></div>

<div class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
    @foreach([['organizations', $stats['organizations']], ['active_organizations', $stats['active']], ['branches', $stats['branches']], ['members', $stats['memberships']]] as [$label, $value])
        <article class="panel p-5"><span class="text-sm text-slate-500">{{ __('admin.'.$label) }}</span><strong class="mt-2 block text-3xl text-slate-950">{{ number_format($value) }}</strong></article>
    @endforeach
</div>

<form class="panel mb-5 grid gap-3 p-4 md:grid-cols-[1fr_190px_auto]" method="GET">
    <label class="field"><span class="sr-only">{{ __('admin.search_companies') }}</span><input type="search" name="q" value="{{ request('q') }}" placeholder="{{ __('admin.search_companies') }}"></label>
    <label class="field"><span class="sr-only">{{ __('admin.status') }}</span><select name="status"><option value="">{{ __('admin.all_statuses') }}</option><option value="active" @selected(request('status') === 'active')>{{ __('admin.active') }}</option><option value="inactive" @selected(request('status') === 'inactive')>{{ __('admin.inactive') }}</option></select></label>
    <button class="btn btn-dark" type="submit">{{ __('admin.filter') }}</button>
</form>

<section class="panel overflow-hidden"><div class="overflow-x-auto"><table class="data-table min-w-[980px]">
    <thead><tr><th>{{ __('admin.company') }}</th><th>{{ __('admin.modules') }}</th><th>{{ __('admin.branches') }}</th><th>{{ __('admin.members') }}</th><th>{{ __('admin.products') }}</th><th>{{ __('admin.sales') }}</th><th>{{ __('admin.status') }}</th><th>{{ __('admin.created') }}</th></tr></thead>
    <tbody>
    @forelse($organizations as $organization)
        <tr>
            <td><strong class="block text-slate-950">{{ $organization->name }}</strong><small class="text-slate-400">{{ $organization->email ?: $organization->slug }}</small></td>
            <td><div class="flex flex-wrap gap-1">@forelse($organization->modules ?? [] as $module)<span class="badge bg-slate-100 text-slate-700">{{ $module }}</span>@empty<span>—</span>@endforelse</div></td>
            <td>{{ $organization->branches_count }}</td><td>{{ $organization->memberships_count }}</td><td>{{ $organization->products_count }}</td><td>{{ $organization->sales_count }}</td>
            <td><span @class(['badge', 'bg-emerald-100 text-emerald-700' => $organization->is_active, 'bg-slate-100 text-slate-600' => !$organization->is_active])>{{ $organization->is_active ? __('admin.active') : __('admin.inactive') }}</span></td>
            <td>{{ $organization->created_at?->format('d.m.Y') }}</td>
        </tr>
    @empty<tr><td colspan="8" class="py-16 text-center text-slate-500">{{ __('admin.no_organizations') }}</td></tr>@endforelse
    </tbody>
</table></div></section>
<div class="mt-5">{{ $organizations->links() }}</div>
@endsection
