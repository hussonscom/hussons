@extends('layouts.app')

@section('title', __('operations.inventory_title'))

@section('content')
<div class="page-head"><div><p class="text-slate-500">{{ __('operations.inventory_subtitle') }}</p></div><div class="flex flex-wrap gap-2"><a class="btn" href="{{ route('inventory.serials') }}">{{ __('operations.serial_registry') }}</a><a class="btn btn-primary" href="{{ route('inventory.create') }}">+ {{ __('operations.receive_stock') }}</a></div></div>

<div class="grid gap-6 xl:grid-cols-[1fr_340px]">
    <section><form class="panel mb-4 flex gap-3 p-4" method="GET"><label class="field flex-1"><span class="sr-only">{{ __('ui.search') }}</span><input type="search" name="q" value="{{ request('q') }}" placeholder="{{ __('operations.search_products') }}"></label><button class="btn btn-dark" type="submit">{{ __('operations.filter') }}</button></form>
        <div class="panel overflow-hidden"><div class="overflow-x-auto"><table class="data-table min-w-[760px]"><thead><tr><th>{{ __('operations.product') }}</th><th>{{ __('operations.variant') }}</th><th>{{ __('operations.warehouse') }}</th><th>{{ __('operations.quantity') }}</th><th>{{ __('operations.reserved') }}</th><th>{{ __('operations.available') }}</th></tr></thead><tbody>
        @forelse($stocks as $stock)<tr><td><strong class="block">{{ $stock->product->localizedName() }}</strong><small class="font-mono text-slate-400">{{ $stock->product->sku }}</small></td><td>{{ $stock->variant?->model ?? '—' }} @if($stock->variant?->color)<small class="block text-slate-400">{{ $stock->variant->color }} · {{ $stock->variant->memory_capacity }}</small>@endif</td><td>{{ $stock->warehouse->name }}</td><td class="font-semibold">{{ $stock->quantity }}</td><td>{{ $stock->reserved_quantity }}</td><td @class(['font-semibold','text-rose-600' => $stock->available_quantity <= $stock->product->min_stock])>{{ $stock->available_quantity }}</td></tr>
        @empty<tr><td colspan="6" class="py-16 text-center text-slate-500">{{ __('operations.no_attention') }}</td></tr>@endforelse
        </tbody></table></div></div><div class="mt-5">{{ $stocks->links() }}</div>
    </section>
    <aside class="panel h-fit overflow-hidden"><header class="border-b border-slate-100 p-5"><h2 class="font-semibold">{{ __('operations.expiring') }}</h2><p class="text-sm text-slate-500">FEFO · 60</p></header><div class="divide-y divide-slate-100">
        @forelse($expiringLots as $lot)<div class="p-4"><div class="flex items-start justify-between gap-4"><span><strong class="block text-sm">{{ $lot->product->localizedName() }}</strong><small class="font-mono text-slate-400">{{ $lot->batch_number }}</small></span><span @class(['badge','bg-rose-100 text-rose-700' => $lot->isExpired(),'bg-amber-100 text-amber-800' => !$lot->isExpired()])>{{ $lot->expiry_date?->format('d.m.Y') ?? '—' }}</span></div><div class="mt-2 flex justify-between text-xs text-slate-500"><span>{{ __('operations.quantity') }}</span><strong>{{ $lot->quantity_available }}</strong></div></div>
        @empty<p class="p-8 text-center text-sm text-slate-500">{{ __('operations.no_attention') }}</p>@endforelse
    </div></aside>
</div>
@endsection
