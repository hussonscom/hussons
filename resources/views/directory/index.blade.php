@extends('layouts.app')

@section('title', __('directory.title'))

@section('content')
@php
    $directoryRole = $currentMembership?->role;
    $canManageBrands = in_array($directoryRole, ['owner', 'admin', 'branch_manager'], true);
    $canManageSuppliers = in_array($directoryRole, ['owner', 'admin', 'branch_manager', 'warehouse'], true);
    $canManageCustomers = in_array($directoryRole, ['owner', 'admin', 'branch_manager', 'cashier', 'pharmacist'], true);
@endphp
<div class="page-head">
    <div><p class="text-slate-500">{{ __('directory.subtitle') }}</p></div>
    <form method="GET" action="{{ route('directory.index') }}" class="flex gap-2">
        <input type="hidden" name="tab" value="{{ $tab }}">
        <label class="field"><span class="sr-only">{{ __('directory.search') }}</span><input type="search" name="q" value="{{ request('q') }}" placeholder="{{ __('directory.search') }}"></label>
        <button class="btn" type="submit">{{ __('directory.search_action') }}</button>
    </form>
</div>

<nav class="mb-5 flex flex-wrap gap-2" aria-label="{{ __('directory.title') }}">
    @foreach(['brands', 'suppliers', 'customers'] as $directoryTab)
        <a href="{{ route('directory.index', ['tab' => $directoryTab]) }}" @class(['btn', 'btn-primary' => $tab === $directoryTab])>{{ __('directory.'.$directoryTab) }}</a>
    @endforeach
</nav>

@if($tab === 'brands')
    <section @class(['grid gap-5', 'xl:grid-cols-[340px_1fr]' => $canManageBrands])>
        @if($canManageBrands)
        <form method="POST" action="{{ route('directory.brands.store') }}" class="panel h-fit p-5">
            @csrf
            <h2 class="mb-4 text-lg font-semibold">{{ __('directory.new_brand') }}</h2>
            <label class="field"><span>{{ __('directory.name') }}</span><input name="name" value="{{ old('name') }}" required maxlength="160"></label>
            <button class="btn btn-primary mt-4 w-full" type="submit">{{ __('directory.create') }}</button>
        </form>
        @endif
        <section class="panel overflow-hidden">
            <div class="table-wrap"><table class="data-table"><thead><tr><th>{{ __('directory.name') }}</th><th>{{ __('directory.status') }}</th><th>{{ __('directory.actions') }}</th></tr></thead><tbody>
            @forelse($brands as $brand)
                <tr><td><strong>{{ $brand->name }}</strong><br><small class="text-slate-400">{{ $brand->slug }}</small></td><td><span class="badge {{ $brand->is_active ? 'badge-success' : '' }}">{{ $brand->is_active ? __('directory.active') : __('directory.archived') }}</span></td><td>
                    @if($canManageBrands)
                    <details><summary class="btn">{{ __('directory.edit') }}</summary><form method="POST" action="{{ route('directory.brands.update', $brand) }}" class="mt-3 grid gap-3 rounded-xl bg-slate-50 p-3">@csrf @method('PATCH')<label class="field"><span>{{ __('directory.name') }}</span><input name="name" value="{{ $brand->name }}" required></label><label class="flex items-center gap-2"><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" @checked($brand->is_active)> {{ __('directory.active') }}</label><button class="btn btn-primary" type="submit">{{ __('directory.save') }}</button></form></details>
                    @if($brand->is_active)<form method="POST" action="{{ route('directory.brands.archive', $brand) }}" class="mt-2">@csrf @method('DELETE')<button class="text-sm font-semibold text-rose-600" type="submit">{{ __('directory.archive') }}</button></form>@endif
                    @else — @endif
                </td></tr>
            @empty<tr><td colspan="3" class="text-center text-slate-500">{{ __('directory.no_brands') }}</td></tr>@endforelse
            </tbody></table></div><div class="p-4">{{ $brands->links() }}</div>
        </section>
    </section>
@elseif($tab === 'suppliers')
    <section class="space-y-5">
        @if($canManageSuppliers)<details class="panel p-5" open><summary class="cursor-pointer text-lg font-semibold">{{ __('directory.new_supplier') }}</summary>
            <form method="POST" action="{{ route('directory.suppliers.store') }}" class="mt-5 grid gap-4 md:grid-cols-2 xl:grid-cols-3">@csrf
                @include('directory.partials.supplier-fields', ['supplier' => null])
                <div class="md:col-span-2 xl:col-span-3"><button class="btn btn-primary" type="submit">{{ __('directory.create') }}</button></div>
            </form>
        </details>@endif
        <section class="grid gap-3">
            @forelse($suppliers as $supplier)
                <details class="panel p-5"><summary class="flex cursor-pointer items-center justify-between gap-4"><span><strong>{{ $supplier->name }}</strong><small class="mt-1 block text-slate-500">{{ $supplier->tax_id ?: '—' }} · {{ $supplier->phone ?: $supplier->email ?: '—' }}</small></span><span class="badge {{ $supplier->is_active ? 'badge-success' : '' }}">{{ $supplier->is_active ? __('directory.active') : __('directory.archived') }}</span></summary>
                    @if($canManageSuppliers)<form method="POST" action="{{ route('directory.suppliers.update', $supplier) }}" class="mt-5 grid gap-4 border-t border-slate-100 pt-5 md:grid-cols-2 xl:grid-cols-3">@csrf @method('PATCH')
                        @include('directory.partials.supplier-fields', ['supplier' => $supplier])
                        <label class="flex items-center gap-2"><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" @checked($supplier->is_active)> {{ __('directory.active') }}</label>
                        <div class="md:col-span-2 xl:col-span-3"><button class="btn btn-primary" type="submit">{{ __('directory.save') }}</button></div>
                    </form>
                    @if($supplier->is_active)<form method="POST" action="{{ route('directory.suppliers.archive', $supplier) }}" class="mt-3">@csrf @method('DELETE')<button class="btn text-rose-600" type="submit">{{ __('directory.archive') }}</button></form>@endif @endif
                </details>
            @empty<div class="panel p-8 text-center text-slate-500">{{ __('directory.no_suppliers') }}</div>@endforelse
            {{ $suppliers->links() }}
        </section>
    </section>
@else
    <section class="space-y-5">
        @if($canManageCustomers)<details class="panel p-5" open><summary class="cursor-pointer text-lg font-semibold">{{ __('directory.new_customer') }}</summary>
            <form method="POST" action="{{ route('directory.customers.store') }}" class="mt-5 grid gap-4 md:grid-cols-2 xl:grid-cols-3">@csrf
                @include('directory.partials.customer-fields', ['customer' => null])
                <div class="md:col-span-2 xl:col-span-3"><button class="btn btn-primary" type="submit">{{ __('directory.create') }}</button></div>
            </form>
        </details>@endif
        <section class="grid gap-3">
            @forelse($customers as $customer)
                <details class="panel p-5"><summary class="flex cursor-pointer items-center justify-between gap-4"><span><strong>{{ $customer->name }}</strong><small class="mt-1 block text-slate-500">{{ $customer->phone ?: $customer->email ?: '—' }}</small></span><span class="badge {{ $customer->is_active ? 'badge-success' : '' }}">{{ $customer->is_active ? __('directory.active') : __('directory.archived') }}</span></summary>
                    @if($canManageCustomers)<form method="POST" action="{{ route('directory.customers.update', $customer) }}" class="mt-5 grid gap-4 border-t border-slate-100 pt-5 md:grid-cols-2 xl:grid-cols-3">@csrf @method('PATCH')
                        @include('directory.partials.customer-fields', ['customer' => $customer])
                        <label class="flex items-center gap-2"><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" @checked($customer->is_active)> {{ __('directory.active') }}</label>
                        <div class="md:col-span-2 xl:col-span-3"><button class="btn btn-primary" type="submit">{{ __('directory.save') }}</button></div>
                    </form>
                    @if($customer->is_active)<form method="POST" action="{{ route('directory.customers.archive', $customer) }}" class="mt-3">@csrf @method('DELETE')<button class="btn text-rose-600" type="submit">{{ __('directory.archive') }}</button></form>@endif @endif
                </details>
            @empty<div class="panel p-8 text-center text-slate-500">{{ __('directory.no_customers') }}</div>@endforelse
            {{ $customers->links() }}
        </section>
    </section>
@endif
@endsection
