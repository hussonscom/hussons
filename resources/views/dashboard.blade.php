@extends('layouts.app')

@section('title', __('ui.dashboard'))

@section('content')
@php
    $role = $currentMembership?->role;
    $canSell = in_array($role, ['owner', 'admin', 'branch_manager', 'cashier', 'pharmacist'], true);
    $canManageInventory = in_array($role, ['owner', 'admin', 'branch_manager', 'warehouse'], true);
    $canViewPharmacy = in_array($role, ['owner', 'admin', 'branch_manager', 'pharmacist', 'auditor'], true)
        && (($currentOrganization->modules ?? []) === [] || array_intersect($currentOrganization->modules ?? [], ['pharmacy']) !== []);
@endphp
<div class="page-head">
    <div><span class="eyebrow">{{ $branch->name }}</span><p class="mt-2 text-slate-500">{{ __('operations.dashboard_subtitle') }}</p></div>
    @if($canSell)<a class="btn btn-primary" href="{{ route('sales.create') }}">+ {{ __('operations.new_sale') }}</a>@endif
</div>

<section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
    @foreach ([
        [__('operations.today_revenue'), number_format((float) $metrics['today_revenue'], 2).' ₼', 'bg-emerald-500'],
        [__('operations.today_sales'), number_format($metrics['today_sales']), 'bg-sky-500'],
        [__('operations.active_products'), number_format($metrics['active_products']), 'bg-violet-500'],
        [__('operations.stock_units'), number_format((float) $metrics['stock_units'], 3), 'bg-amber-500'],
    ] as [$label, $value, $dotClass])
        <article class="panel p-5">
            <div class="flex items-center justify-between"><span class="text-sm text-slate-500">{{ $label }}</span><i class="h-2.5 w-2.5 rounded-full {{ $dotClass }}"></i></div>
            <strong class="mt-3 block text-3xl tracking-tight text-slate-950">{{ $value }}</strong>
        </article>
    @endforeach
</section>

<section class="mt-6 grid gap-6 xl:grid-cols-2">
    <article class="panel overflow-hidden">
        <header class="flex items-center justify-between border-b border-slate-100 p-5"><div><h2 class="text-lg font-semibold text-slate-950">{{ __('operations.low_stock') }}</h2><p class="text-sm text-slate-500">{{ __('operations.minimum') }}</p></div>@if($canManageInventory)<a class="text-sm font-semibold text-blue-600" href="{{ route('inventory.index') }}">{{ __('ui.inventory') }} →</a>@endif</header>
        <div class="overflow-x-auto">
            <table class="data-table min-w-[620px]"><thead><tr><th>{{ __('operations.product') }}</th><th>{{ __('operations.warehouse') }}</th><th>{{ __('operations.quantity') }}</th><th>{{ __('operations.minimum') }}</th></tr></thead>
                <tbody>
                @forelse ($lowStock as $stock)
                    <tr><td><strong>{{ $stock->product->localizedName() }}</strong><small class="block text-slate-400">{{ $stock->product->sku }}</small></td><td>{{ $stock->warehouse->name }}</td><td class="font-semibold text-rose-600">{{ $stock->quantity }}</td><td>{{ $stock->product->min_stock }}</td></tr>
                @empty <tr><td class="py-12 text-center text-slate-500" colspan="4">{{ __('operations.no_attention') }}</td></tr> @endforelse
                </tbody>
            </table>
        </div>
    </article>

    @if($canViewPharmacy)<article class="panel overflow-hidden">
        <header class="flex items-center justify-between border-b border-slate-100 p-5"><div><h2 class="text-lg font-semibold text-slate-950">{{ __('operations.expiring') }}</h2><p class="text-sm text-slate-500">FEFO · 45</p></div><a class="text-sm font-semibold text-blue-600" href="{{ route('pharmacy.index') }}">{{ __('ui.pharmacy_module') }} →</a></header>
        <div class="overflow-x-auto">
            <table class="data-table min-w-[620px]"><thead><tr><th>{{ __('operations.product') }}</th><th>{{ __('operations.batch') }}</th><th>{{ __('operations.expiry_date') }}</th><th>{{ __('operations.quantity') }}</th></tr></thead>
                <tbody>
                @forelse ($expiringLots as $lot)
                    <tr><td><strong>{{ $lot->product->localizedName() }}</strong></td><td class="font-mono text-xs">{{ $lot->batch_number }}</td><td><span @class(['badge', 'bg-rose-100 text-rose-700' => $lot->expiry_date->diffInDays(today()) <= 7, 'bg-amber-100 text-amber-800' => $lot->expiry_date->diffInDays(today()) > 7])>{{ $lot->expiry_date->format('d.m.Y') }}</span></td><td>{{ $lot->quantity_available }}</td></tr>
                @empty <tr><td class="py-12 text-center text-slate-500" colspan="4">{{ __('operations.no_attention') }}</td></tr> @endforelse
                </tbody>
            </table>
        </div>
    </article>@endif
</section>

<section class="mt-6 grid gap-6 xl:grid-cols-[1.4fr_.8fr]">
    <article class="panel overflow-hidden">
        <header class="border-b border-slate-100 p-5"><h2 class="text-lg font-semibold text-slate-950">{{ __('operations.recent_sales') }}</h2></header>
        <div class="overflow-x-auto"><table class="data-table min-w-[650px]"><thead><tr><th>{{ __('operations.number') }}</th><th>{{ __('operations.customer') }}</th><th>{{ __('operations.cashier') }}</th><th>{{ __('operations.status') }}</th><th class="text-right">{{ __('operations.total') }}</th></tr></thead><tbody>
        @forelse ($recentSales as $sale)
            <tr><td>@if($canSell)<a class="font-mono text-xs font-semibold text-blue-600" href="{{ route('sales.show', $sale) }}">{{ $sale->number }}</a>@else<span class="font-mono text-xs font-semibold">{{ $sale->number }}</span>@endif</td><td>{{ $sale->customer?->name ?? '—' }}</td><td>{{ $sale->user?->name }}</td><td><span class="badge bg-slate-100 text-slate-700">{{ $sale->status }}</span></td><td class="text-right font-semibold">{{ number_format((float) $sale->total, 2) }} ₼</td></tr>
        @empty <tr><td colspan="5" class="py-12 text-center text-slate-500">{{ __('operations.no_sales') }}</td></tr> @endforelse
        </tbody></table></div>
    </article>
    <article class="panel p-5"><h2 class="text-lg font-semibold text-slate-950">{{ __('operations.top_products') }}</h2><div class="mt-5 space-y-4">
        @forelse ($topProducts as $item)
            <div><div class="flex items-start justify-between gap-4"><span><strong class="block text-sm text-slate-900">{{ $item->name }}</strong><small class="text-slate-500">{{ $item->sold_quantity }} {{ __('operations.sold') }}</small></span><strong class="text-sm">{{ number_format((float) $item->revenue, 2) }} ₼</strong></div><div class="mt-2 h-1.5 overflow-hidden rounded-full bg-slate-100"><i class="block h-full rounded-full bg-blue-500" style="width: {{ min(100, max(8, (float) $item->sold_quantity * 4)) }}%"></i></div></div>
        @empty <p class="py-12 text-center text-sm text-slate-500">{{ __('operations.no_sales') }}</p> @endforelse
    </div></article>
</section>
@endsection
