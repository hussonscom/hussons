@extends('layouts.app')

@php
    $locale = app()->getLocale();
    $translations = [
        'az' => [
            'title' => 'Aptek nəzarəti', 'subtitle' => 'Partiyalar, son istifadə tarixləri və resept nəzarəti bir ekranda.',
            'available' => 'Satışa hazır vahid', 'expiring' => 'Yaxınlaşan tarix', 'expired' => 'Bloklanmış partiya',
            'search' => 'Dərman, barkod və ya partiya axtar', 'all' => 'Bütün partiyalar', 'active' => 'Aktiv',
            'apply' => 'Tətbiq et', 'batch' => 'Partiya', 'product' => 'Məhsul', 'warehouse' => 'Anbar',
            'stock' => 'Qalıq', 'expiry' => 'Son istifadə', 'status' => 'Status', 'fefo' => 'Növbəti satış',
            'sellable' => 'Satışa açıq', 'near' => 'Tarix yaxınlaşır', 'blocked' => 'Satış bloklanıb',
            'empty' => 'Seçilmiş filtr üzrə partiya tapılmadı.', 'prescription' => 'Resept nəzarəti',
            'prescription_products' => 'Resept tələb edən məhsullar', 'no_rx_products' => 'Resept tələb edən məhsul qeydə alınmayıb.',
            'recent_rx' => 'Son resept qeydləri', 'rx_number' => 'Resept', 'prescriber' => 'Həkim', 'sale' => 'Satış',
            'verified' => 'Təsdiqlənib', 'pending' => 'Yoxlama gözləyir', 'no_rx' => 'Resept qeydi yoxdur.',
            'warning_days' => 'Xəbərdarlıq günü', 'days' => 'gün',
        ],
        'ru' => [
            'title' => 'Контроль аптеки', 'subtitle' => 'Партии, сроки годности и рецептурный контроль на одном экране.',
            'available' => 'Единиц доступно', 'expiring' => 'Срок приближается', 'expired' => 'Заблокировано партий',
            'search' => 'Поиск лекарства, штрихкода или партии', 'all' => 'Все партии', 'active' => 'Активные',
            'apply' => 'Применить', 'batch' => 'Партия', 'product' => 'Товар', 'warehouse' => 'Склад',
            'stock' => 'Остаток', 'expiry' => 'Годен до', 'status' => 'Статус', 'fefo' => 'Следующая продажа',
            'sellable' => 'Доступно к продаже', 'near' => 'Срок приближается', 'blocked' => 'Продажа заблокирована',
            'empty' => 'По выбранному фильтру партии не найдены.', 'prescription' => 'Рецептурный контроль',
            'prescription_products' => 'Товары по рецепту', 'no_rx_products' => 'Рецептурные товары пока не добавлены.',
            'recent_rx' => 'Последние рецепты', 'rx_number' => 'Рецепт', 'prescriber' => 'Врач', 'sale' => 'Продажа',
            'verified' => 'Проверен', 'pending' => 'Ожидает проверки', 'no_rx' => 'Рецептов пока нет.',
            'warning_days' => 'Порог предупреждения', 'days' => 'дн.',
        ],
        'en' => [
            'title' => 'Pharmacy control', 'subtitle' => 'Lots, expiry dates and prescription control in one workspace.',
            'available' => 'Units ready for sale', 'expiring' => 'Expiring lots', 'expired' => 'Blocked lots',
            'search' => 'Search medicine, barcode or batch', 'all' => 'All lots', 'active' => 'Active',
            'apply' => 'Apply', 'batch' => 'Batch', 'product' => 'Product', 'warehouse' => 'Warehouse',
            'stock' => 'Stock', 'expiry' => 'Expiry', 'status' => 'Status', 'fefo' => 'Sell next',
            'sellable' => 'Available for sale', 'near' => 'Expiry approaching', 'blocked' => 'Sale blocked',
            'empty' => 'No lots match the selected filters.', 'prescription' => 'Prescription control',
            'prescription_products' => 'Prescription products', 'no_rx_products' => 'No prescription products have been added.',
            'recent_rx' => 'Recent prescriptions', 'rx_number' => 'Prescription', 'prescriber' => 'Prescriber', 'sale' => 'Sale',
            'verified' => 'Verified', 'pending' => 'Verification pending', 'no_rx' => 'No prescription records yet.',
            'warning_days' => 'Warning threshold', 'days' => 'days',
        ],
    ];
    $copy = $translations[$locale] ?? $translations['en'];
    $productName = static function ($product) use ($locale) {
        $field = in_array($locale, ['az', 'ru', 'en'], true) ? 'name_'.$locale : 'name_az';
        return $product?->{$field} ?: $product?->name_az ?: $product?->name_en ?: '—';
    };
@endphp

@section('title', $copy['title'])

@section('content')
    <div class="page-head">
        <div>
            <p class="eyebrow">Hussons One · Pharma</p>
            <h1>{{ $copy['title'] }}</h1>
            <p>{{ $copy['subtitle'] }}</p>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-600 shadow-sm">
            {{ $copy['warning_days'] }}: <strong class="text-slate-950">{{ $warningDays }} {{ $copy['days'] }}</strong>
        </div>
    </div>

    <div class="grid gap-4 md:grid-cols-3">
        <div class="panel p-5">
            <span class="text-sm text-slate-500">{{ $copy['available'] }}</span>
            <strong class="mt-3 block text-3xl font-semibold tracking-tight text-slate-950">{{ number_format((float) $stats['available'], 0, '.', ' ') }}</strong>
            <div class="mt-4 h-1.5 rounded-full bg-emerald-100"><div class="h-full w-4/5 rounded-full bg-emerald-500"></div></div>
        </div>
        <div class="panel p-5">
            <span class="text-sm text-slate-500">{{ $copy['expiring'] }}</span>
            <strong class="mt-3 block text-3xl font-semibold tracking-tight text-amber-700">{{ $stats['expiring'] }}</strong>
            <div class="mt-4 h-1.5 rounded-full bg-amber-100"><div class="h-full w-1/2 rounded-full bg-amber-500"></div></div>
        </div>
        <div class="panel p-5">
            <span class="text-sm text-slate-500">{{ $copy['expired'] }}</span>
            <strong class="mt-3 block text-3xl font-semibold tracking-tight text-rose-700">{{ $stats['expired'] }}</strong>
            <div class="mt-4 h-1.5 rounded-full bg-rose-100"><div class="h-full w-1/3 rounded-full bg-rose-500"></div></div>
        </div>
    </div>

    <section class="panel mt-6 overflow-hidden">
        <form method="GET" class="grid gap-3 border-b border-slate-200 p-4 md:grid-cols-[1fr_180px_170px_auto]">
            <input class="field" type="search" name="search" value="{{ request('search') }}" placeholder="{{ $copy['search'] }}">
            <select class="field" name="status">
                <option value="">{{ $copy['all'] }}</option>
                <option value="active" @selected(request('status') === 'active')>{{ $copy['active'] }}</option>
                <option value="expiring" @selected(request('status') === 'expiring')>{{ $copy['expiring'] }}</option>
                <option value="expired" @selected(request('status') === 'expired')>{{ $copy['expired'] }}</option>
            </select>
            <input class="field" type="number" min="1" max="365" name="warning_days" value="{{ $warningDays }}" aria-label="{{ $copy['warning_days'] }}">
            <button class="btn btn-primary" type="submit">{{ $copy['apply'] }}</button>
        </form>

        <div class="overflow-x-auto">
            <table class="data-table min-w-[900px]">
                <thead>
                    <tr>
                        <th>{{ $copy['product'] }}</th>
                        <th>{{ $copy['batch'] }}</th>
                        <th>{{ $copy['warehouse'] }}</th>
                        <th>{{ $copy['stock'] }}</th>
                        <th>{{ $copy['expiry'] }}</th>
                        <th>{{ $copy['status'] }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($lots as $lot)
                        @php
                            $expiry = \Carbon\CarbonImmutable::parse($lot->expiry_date)->startOfDay();
                            $isExpired = $expiry->lt($today);
                            $isNear = ! $isExpired && $expiry->lte($today->addDays($warningDays));
                            $isFefo = in_array($lot->id, $fefoLotIds, true);
                        @endphp
                        <tr class="{{ $isExpired ? 'bg-rose-50/60' : '' }}">
                            <td>
                                <div class="font-semibold text-slate-950">{{ $productName($lot->product) }}</div>
                                <div class="mt-0.5 text-xs text-slate-500">{{ $lot->product?->barcode ?: $lot->product?->sku }}</div>
                            </td>
                            <td><span class="font-mono text-xs font-semibold text-slate-700">{{ $lot->batch_number }}</span></td>
                            <td>{{ $lot->warehouse?->name ?? '—' }}</td>
                            <td class="font-semibold">{{ number_format((float) $lot->quantity_available, 2, '.', ' ') }}</td>
                            <td>
                                <span class="font-medium {{ $isExpired ? 'text-rose-700' : ($isNear ? 'text-amber-700' : 'text-slate-900') }}">
                                    {{ $expiry->format('d.m.Y') }}
                                </span>
                            </td>
                            <td>
                                @if ($isExpired)
                                    <span class="badge bg-rose-100 text-rose-800">{{ $copy['blocked'] }}</span>
                                @elseif ($isNear)
                                    <span class="badge bg-amber-100 text-amber-800">{{ $copy['near'] }}</span>
                                @else
                                    <span class="badge bg-emerald-100 text-emerald-800">{{ $copy['sellable'] }}</span>
                                @endif
                                @if ($isFefo)
                                    <span class="badge ml-1 bg-sky-100 text-sky-800">FEFO · {{ $copy['fefo'] }}</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="py-12 text-center text-slate-500">{{ $copy['empty'] }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($lots->hasPages())
            <div class="border-t border-slate-200 p-4">{{ $lots->links() }}</div>
        @endif
    </section>

    <div class="mt-6 grid gap-6 xl:grid-cols-2">
        <section class="panel p-5">
            <div class="mb-4">
                <p class="eyebrow">{{ $copy['prescription'] }}</p>
                <h2 class="text-xl font-semibold text-slate-950">{{ $copy['prescription_products'] }}</h2>
            </div>
            <div class="space-y-2">
                @forelse ($prescriptionProducts as $product)
                    <div class="flex items-center justify-between rounded-xl border border-slate-200 px-4 py-3">
                        <div>
                            <p class="font-medium text-slate-950">{{ $productName($product) }}</p>
                            <p class="text-xs text-slate-500">{{ data_get($product->metadata, 'active_ingredient') ?: data_get($product->metadata, 'dosage') }}</p>
                        </div>
                        <span class="badge bg-violet-100 text-violet-800">Rx</span>
                    </div>
                @empty
                    <p class="rounded-xl bg-slate-50 px-4 py-8 text-center text-sm text-slate-500">{{ $copy['no_rx_products'] }}</p>
                @endforelse
            </div>
        </section>

        <section class="panel overflow-hidden">
            <div class="border-b border-slate-200 p-5">
                <p class="eyebrow">{{ $copy['prescription'] }}</p>
                <h2 class="text-xl font-semibold text-slate-950">{{ $copy['recent_rx'] }}</h2>
            </div>
            <div class="divide-y divide-slate-100">
                @forelse ($prescriptions as $prescription)
                    <div class="flex items-center justify-between gap-4 p-4">
                        <div>
                            <p class="font-semibold text-slate-950">{{ $copy['rx_number'] }} #{{ $prescription->number }}</p>
                            <p class="mt-1 text-xs text-slate-500">
                                {{ $copy['prescriber'] }}: {{ $prescription->prescriber_name ?: '—' }}
                                @if ($prescription->sale) · {{ $copy['sale'] }} #{{ $prescription->sale->number }} @endif
                            </p>
                        </div>
                        @if ($prescription->verified_at)
                            <span class="badge bg-emerald-100 text-emerald-800">{{ $copy['verified'] }}</span>
                        @else
                            <span class="badge bg-amber-100 text-amber-800">{{ $copy['pending'] }}</span>
                        @endif
                    </div>
                @empty
                    <p class="p-8 text-center text-sm text-slate-500">{{ $copy['no_rx'] }}</p>
                @endforelse
            </div>
            @if ($prescriptions->hasPages())
                <div class="border-t border-slate-200 p-4">{{ $prescriptions->links() }}</div>
            @endif
        </section>
    </div>
@endsection
