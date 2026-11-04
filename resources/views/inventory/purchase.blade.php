@extends('layouts.app')

@section('title', __('operations.purchase_title').' '.$purchase->number)

@section('content')
<div class="page-head"><div><span class="badge bg-emerald-100 text-emerald-800">{{ __('operations.received') }}</span><p class="mt-2 text-sm text-slate-500">{{ $purchase->received_at?->format('d.m.Y H:i') }} · {{ $purchase->warehouse->name }}</p></div><a class="btn" href="{{ route('inventory.index') }}">← {{ __('ui.inventory') }}</a></div>
<section class="panel overflow-hidden"><div class="grid gap-4 border-b border-slate-100 p-5 sm:grid-cols-3"><div><small class="text-slate-400">{{ __('operations.supplier') }}</small><strong class="block">{{ $purchase->supplier?->name ?? '—' }}</strong></div><div><small class="text-slate-400">{{ __('operations.cashier') }}</small><strong class="block">{{ $purchase->user->name }}</strong></div><div><small class="text-slate-400">{{ __('operations.total') }}</small><strong class="block text-xl">{{ number_format((float)$purchase->total, 2) }} ₼</strong></div></div>
<div class="overflow-x-auto"><table class="data-table min-w-[700px]"><thead><tr><th>{{ __('operations.product') }}</th><th>SKU</th><th>{{ __('operations.quantity') }}</th><th>{{ __('operations.unit_cost') }}</th><th>{{ __('operations.total') }}</th></tr></thead><tbody>@foreach($purchase->items as $item)<tr><td>{{ $item->product->localizedName() }}</td><td class="font-mono text-xs">{{ $item->sku }}</td><td>{{ $item->quantity_received }}</td><td>{{ number_format((float)$item->unit_cost, 2) }} ₼</td><td class="font-semibold">{{ number_format((float)$item->total, 2) }} ₼</td></tr>@endforeach</tbody></table></div></section>
@endsection
