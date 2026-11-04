@extends('layouts.app')

@section('title', __('operations.serials_title'))

@section('content')
<div class="page-head"><div><p class="text-slate-500">IMEI · Serial · Warranty</p></div><a class="btn" href="{{ route('inventory.index') }}">← {{ __('ui.inventory') }}</a></div>
<form class="panel mb-4 flex gap-3 p-4" method="GET"><label class="field flex-1"><span class="sr-only">{{ __('ui.search') }}</span><input type="search" name="q" value="{{ request('q') }}" placeholder="IMEI / Serial"></label><button class="btn btn-dark">{{ __('operations.filter') }}</button></form>
<div class="panel overflow-hidden"><div class="overflow-x-auto"><table class="data-table min-w-[1050px]"><thead><tr><th>{{ __('operations.product') }}</th><th>{{ __('operations.serial') }}</th><th>{{ __('operations.imei') }} 1</th><th>{{ __('operations.imei') }} 2</th><th>{{ __('operations.warehouse') }}</th><th>{{ __('operations.status') }}</th><th>{{ __('operations.warranty') }}</th><th>{{ __('returns.inspection') }}</th></tr></thead><tbody>
@forelse($units as $unit)<tr><td><strong>{{ $unit->product->localizedName() }}</strong><small class="block text-slate-400">{{ $unit->variant?->model }}</small></td><td class="font-mono text-xs">{{ $unit->serial_number ?? '—' }}</td><td class="font-mono text-xs">{{ $unit->imei1 ?? '—' }}</td><td class="font-mono text-xs">{{ $unit->imei2 ?? '—' }}</td><td>{{ $unit->warehouse->name }}</td><td><span @class(['badge', 'bg-amber-100 text-amber-800' => $unit->status === 'returned', 'bg-emerald-100 text-emerald-800' => $unit->status === 'in_stock', 'bg-slate-100 text-slate-700' => !in_array($unit->status, ['returned', 'in_stock'], true)])>{{ __('returns.status_'.$unit->status) }}</span></td><td>{{ $unit->warranty_months }}</td><td>
    @if($unit->status === 'returned' && in_array($currentMembership?->role, ['owner','admin','branch_manager','warehouse'], true))
        <form class="flex min-w-[320px] gap-2" method="POST" action="{{ route('inventory.returns.update', $unit) }}">@csrf @method('PATCH')
            <select class="rounded-lg border border-slate-200 px-2 py-1 text-xs" name="disposition"><option value="in_stock">{{ __('returns.restock') }}</option><option value="service">{{ __('returns.service') }}</option><option value="writeoff">{{ __('returns.writeoff') }}</option></select>
            <input class="min-w-0 rounded-lg border border-slate-200 px-2 py-1 text-xs" name="notes" maxlength="1000" placeholder="{{ __('returns.notes') }}">
            <button class="btn" type="submit">{{ __('returns.apply') }}</button>
        </form>
    @else — @endif
</td></tr>
@empty<tr><td colspan="8" class="py-16 text-center text-slate-500">{{ __('operations.no_attention') }}</td></tr>@endforelse
</tbody></table></div></div><div class="mt-5">{{ $units->links() }}</div>
@endsection
