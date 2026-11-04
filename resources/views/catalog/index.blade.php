@extends('layouts.app')

@section('title', __('operations.catalog_title'))

@section('content')
@php
    $canManageCatalog = in_array($currentMembership?->role, ['owner', 'admin', 'branch_manager'], true);
@endphp
<div class="page-head"><div><p class="text-slate-500">{{ __('operations.catalog_subtitle') }}</p></div>@if($canManageCatalog)<a class="btn btn-primary" href="{{ route('catalog.create') }}">+ {{ __('operations.new_product') }}</a>@endif</div>

<form class="panel mb-5 grid gap-3 p-4 md:grid-cols-[1fr_180px_auto_auto]" method="GET">
    <label class="field"><span class="sr-only">{{ __('operations.search_products') }}</span><input type="search" name="q" value="{{ request('q') }}" placeholder="{{ __('operations.search_products') }}"></label>
    <label class="field"><span class="sr-only">{{ __('operations.type') }}</span><select name="type"><option value="">{{ __('operations.all_types') }}</option>@foreach (['standard','electronic','drug','food','service'] as $type)<option value="{{ $type }}" @selected(request('type') === $type)>{{ ucfirst($type) }}</option>@endforeach</select></label>
    <label class="flex items-center gap-2 rounded-xl border border-slate-200 px-3 text-sm"><input type="checkbox" name="inactive" value="1" @checked(request()->boolean('inactive'))>{{ __('operations.show_inactive') }}</label>
    <button class="btn btn-dark" type="submit">{{ __('operations.filter') }}</button>
</form>

<div class="panel overflow-hidden"><div class="overflow-x-auto"><table class="data-table min-w-[900px]">
    <thead><tr><th>{{ __('operations.product') }}</th><th>{{ __('operations.category') }}</th><th>{{ __('operations.type') }}</th><th>{{ __('operations.tracking') }}</th><th>{{ __('operations.stock') }}</th><th>{{ __('operations.price') }}</th>@if($canManageCatalog)<th class="text-right">{{ __('operations.actions') }}</th>@endif</tr></thead>
    <tbody>
    @forelse ($products as $product)
        <tr>
            <td><strong class="block text-slate-950">{{ $product->localizedName() }}</strong><small class="font-mono text-slate-400">{{ $product->sku }}@if($product->barcode) · {{ $product->barcode }}@endif</small></td>
            <td>{{ $product->category?->localizedName() ?? '—' }}</td>
            <td><span class="badge bg-slate-100 text-slate-700">{{ $product->type }}</span></td>
            <td><span @class(['badge','bg-blue-100 text-blue-700' => $product->tracking_mode === 'serial','bg-violet-100 text-violet-700' => $product->tracking_mode === 'batch','bg-slate-100 text-slate-600' => $product->tracking_mode === 'none'])>{{ $product->tracking_mode }}</span></td>
            <td class="font-semibold">{{ number_format((float) ($product->stocks_sum_quantity ?? 0), 3) }}</td>
            <td class="font-semibold">{{ number_format((float) $product->sale_price, 2) }} ₼</td>
            @if($canManageCatalog)<td><div class="flex justify-end gap-2"><a class="btn" href="{{ route('catalog.edit', $product) }}">{{ __('operations.edit') }}</a>@if($product->is_active)<form method="POST" action="{{ route('catalog.destroy', $product) }}" onsubmit="return confirm('{{ __('operations.archive') }}?')">@csrf @method('DELETE')<button class="btn text-rose-600" type="submit">{{ __('operations.archive') }}</button></form>@endif</div></td>@endif
        </tr>
    @empty <tr><td colspan="{{ $canManageCatalog ? 7 : 6 }}" class="py-16 text-center text-slate-500">{{ __('operations.empty_products') }}</td></tr> @endforelse
    </tbody>
</table></div></div>
<div class="mt-5">{{ $products->links() }}</div>
@endsection
