@extends('layouts.app')

@php
    $locale = app()->getLocale();
    $translations = [
        'az' => [
            'title' => 'Restoran və kafe', 'subtitle' => 'Zal, sifariş və mətbəx axını canlı iş məkanında.',
            'new_order' => 'Yeni sifariş', 'new_table' => 'Masa əlavə et', 'open_orders' => 'Aktiv sifariş',
            'ready_orders' => 'Hazır sifariş', 'occupied' => 'Dolu masa', 'today_revenue' => 'Bugünkü dövriyyə',
            'hall' => 'Zal xəritəsi', 'available' => 'Boş', 'table_occupied' => 'Dolu', 'seats' => 'yer',
            'orders' => 'Sifariş axını', 'all' => 'Hamısı', 'open' => 'Yeni', 'new' => 'Yeni', 'preparing' => 'Hazırlanır',
            'ready' => 'Hazırdır', 'served' => 'Təqdim edilib', 'paid' => 'Ödənilib', 'cancelled' => 'Ləğv edilib',
            'guests' => 'qonaq', 'items' => 'mövqe', 'no_orders' => 'Bu statusda sifariş yoxdur.',
            'start_preparing' => 'Hazırlamağa başla', 'mark_ready' => 'Hazırdır', 'mark_served' => 'Təqdim et',
            'take_payment' => 'Ödənişi tamamla', 'cancel' => 'Ləğv et', 'table_name' => 'Masa adı',
            'seat_count' => 'Yer sayı', 'create' => 'Yarat', 'select_table' => 'Masa seçin', 'notes' => 'Qeyd',
            'guest_count' => 'Qonaq sayı', 'menu_item' => 'Menyu mövqeyi', 'quantity' => 'Miqdar',
            'add_item' => 'Mövqe əlavə et', 'send_kitchen' => 'Mətbəxə göndər', 'close' => 'Bağla',
            'no_tables' => 'Zalda masa yoxdur. İlk masanı əlavə edin.', 'no_menu' => 'Menyu məhsulu yoxdur.',
            'payment_method' => 'Ödəniş üsulu', 'cash' => 'Nağd', 'card' => 'Kart', 'transfer' => 'Bank köçürməsi',
            'amount_received' => 'Alınan məbləğ', 'warehouse' => 'Anbar', 'payment_reference' => 'İstinad (istəyə bağlı)',
            'no_warehouse' => 'Ödəniş üçün filialda aktiv anbar yaradılmalıdır.',
        ],
        'ru' => [
            'title' => 'Ресторан и кафе', 'subtitle' => 'Зал, заказы и кухня в едином рабочем пространстве.',
            'new_order' => 'Новый заказ', 'new_table' => 'Добавить стол', 'open_orders' => 'Активные заказы',
            'ready_orders' => 'Готовые заказы', 'occupied' => 'Занятые столы', 'today_revenue' => 'Выручка сегодня',
            'hall' => 'Карта зала', 'available' => 'Свободен', 'table_occupied' => 'Занят', 'seats' => 'мест',
            'orders' => 'Поток заказов', 'all' => 'Все', 'open' => 'Новый', 'new' => 'Новый', 'preparing' => 'Готовится',
            'ready' => 'Готов', 'served' => 'Подан', 'paid' => 'Оплачен', 'cancelled' => 'Отменён',
            'guests' => 'гостей', 'items' => 'позиций', 'no_orders' => 'Заказов с таким статусом нет.',
            'start_preparing' => 'Начать готовить', 'mark_ready' => 'Готово', 'mark_served' => 'Подать',
            'take_payment' => 'Завершить оплату', 'cancel' => 'Отменить', 'table_name' => 'Название стола',
            'seat_count' => 'Количество мест', 'create' => 'Создать', 'select_table' => 'Выберите стол', 'notes' => 'Комментарий',
            'guest_count' => 'Количество гостей', 'menu_item' => 'Позиция меню', 'quantity' => 'Количество',
            'add_item' => 'Добавить позицию', 'send_kitchen' => 'Отправить на кухню', 'close' => 'Закрыть',
            'no_tables' => 'В зале пока нет столов. Добавьте первый стол.', 'no_menu' => 'В меню пока нет товаров.',
            'payment_method' => 'Способ оплаты', 'cash' => 'Наличные', 'card' => 'Карта', 'transfer' => 'Банковский перевод',
            'amount_received' => 'Полученная сумма', 'warehouse' => 'Склад', 'payment_reference' => 'Ссылка/номер (необязательно)',
            'no_warehouse' => 'Для оплаты нужен активный склад этого филиала.',
        ],
        'en' => [
            'title' => 'Restaurant & cafe', 'subtitle' => 'Floor, orders and kitchen flow in one live workspace.',
            'new_order' => 'New order', 'new_table' => 'Add table', 'open_orders' => 'Open orders',
            'ready_orders' => 'Ready orders', 'occupied' => 'Occupied tables', 'today_revenue' => 'Revenue today',
            'hall' => 'Floor map', 'available' => 'Available', 'table_occupied' => 'Occupied', 'seats' => 'seats',
            'orders' => 'Order flow', 'all' => 'All', 'open' => 'New', 'new' => 'New', 'preparing' => 'Preparing',
            'ready' => 'Ready', 'served' => 'Served', 'paid' => 'Paid', 'cancelled' => 'Cancelled',
            'guests' => 'guests', 'items' => 'items', 'no_orders' => 'No orders have this status.',
            'start_preparing' => 'Start preparing', 'mark_ready' => 'Mark ready', 'mark_served' => 'Mark served',
            'take_payment' => 'Complete payment', 'cancel' => 'Cancel', 'table_name' => 'Table name',
            'seat_count' => 'Seat count', 'create' => 'Create', 'select_table' => 'Select a table', 'notes' => 'Notes',
            'guest_count' => 'Guest count', 'menu_item' => 'Menu item', 'quantity' => 'Quantity',
            'add_item' => 'Add item', 'send_kitchen' => 'Send to kitchen', 'close' => 'Close',
            'no_tables' => 'No tables yet. Add the first table.', 'no_menu' => 'No menu products yet.',
            'payment_method' => 'Payment method', 'cash' => 'Cash', 'card' => 'Card', 'transfer' => 'Bank transfer',
            'amount_received' => 'Amount received', 'warehouse' => 'Warehouse', 'payment_reference' => 'Reference (optional)',
            'no_warehouse' => 'An active branch warehouse is required before payment.',
        ],
    ];
    $copy = $translations[$locale] ?? $translations['en'];
    $productName = static function ($product) use ($locale) {
        $field = in_array($locale, ['az', 'ru', 'en'], true) ? 'name_'.$locale : 'name_az';
        return $product?->{$field} ?: $product?->name_az ?: $product?->name_en ?: $product?->sku ?: '—';
    };
    $statusClass = static fn (string $status): string => match ($status) {
        'open', 'new' => 'bg-sky-100 text-sky-800',
        'preparing' => 'bg-amber-100 text-amber-800',
        'ready' => 'bg-emerald-100 text-emerald-800',
        'served' => 'bg-violet-100 text-violet-800',
        'paid' => 'bg-slate-200 text-slate-700',
        default => 'bg-rose-100 text-rose-800',
    };
    $role = $currentMembership?->role;
    $canManageTables = in_array($role, ['owner', 'admin', 'branch_manager'], true);
    $canCreateOrders = in_array($role, ['owner', 'admin', 'branch_manager', 'cashier'], true);
    $canUseKitchen = in_array($role, ['owner', 'admin', 'branch_manager', 'kitchen'], true);
    $canUseRegister = in_array($role, ['owner', 'admin', 'branch_manager', 'cashier'], true);
@endphp

@section('title', $copy['title'])

@section('content')
    <div class="page-head">
        <div>
            <p class="eyebrow">Hussons One · Food</p>
            <h1>{{ $copy['title'] }}</h1>
            <p>{{ $copy['subtitle'] }}</p>
        </div>
        <div class="flex flex-wrap gap-2">
            @if($canManageTables)<button type="button" class="btn" data-dialog-open="table-dialog">{{ $copy['new_table'] }}</button>@endif
            @if($canCreateOrders)<button type="button" class="btn btn-primary" data-dialog-open="order-dialog" @disabled($tables->isEmpty() || $menuItems->isEmpty())>{{ $copy['new_order'] }}</button>@endif
        </div>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="panel p-5"><span class="text-sm text-slate-500">{{ $copy['open_orders'] }}</span><strong class="mt-2 block text-3xl text-slate-950">{{ $summary['open'] }}</strong></div>
        <div class="panel p-5"><span class="text-sm text-slate-500">{{ $copy['ready_orders'] }}</span><strong class="mt-2 block text-3xl text-emerald-700">{{ $summary['ready'] }}</strong></div>
        <div class="panel p-5"><span class="text-sm text-slate-500">{{ $copy['occupied'] }}</span><strong class="mt-2 block text-3xl text-amber-700">{{ $summary['occupied'] }} / {{ $tables->count() }}</strong></div>
        <div class="panel p-5"><span class="text-sm text-slate-500">{{ $copy['today_revenue'] }}</span><strong class="mt-2 block text-3xl text-slate-950">{{ number_format((float) $summary['revenue'], 2, '.', ' ') }} ₼</strong></div>
    </div>

    <section class="panel mt-6 p-5">
        <div class="mb-5 flex items-center justify-between">
            <h2 class="text-xl font-semibold text-slate-950">{{ $copy['hall'] }}</h2>
            <span class="text-xs font-medium uppercase tracking-widest text-slate-400">{{ $branch->name }}</span>
        </div>
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-6">
            @forelse ($tables as $table)
                @php
                    $busy = $table->active_orders_count > 0;
                @endphp
                <div class="relative overflow-hidden rounded-2xl border p-4 {{ $busy ? 'border-amber-300 bg-amber-50' : 'border-emerald-200 bg-emerald-50/60' }}">
                    <span class="absolute right-3 top-3 h-2.5 w-2.5 rounded-full {{ $busy ? 'bg-amber-500' : 'bg-emerald-500' }}"></span>
                    <p class="font-semibold text-slate-950">{{ $table->name }}</p>
                    <p class="mt-1 text-xs text-slate-500">{{ $table->seats }} {{ $copy['seats'] }}</p>
                    <p class="mt-4 text-xs font-semibold {{ $busy ? 'text-amber-800' : 'text-emerald-800' }}">{{ $busy ? $copy['table_occupied'] : $copy['available'] }}</p>
                </div>
            @empty
                <p class="col-span-full rounded-2xl border border-dashed border-slate-300 px-4 py-10 text-center text-sm text-slate-500">{{ $copy['no_tables'] }}</p>
            @endforelse
        </div>
    </section>

    <section class="mt-6">
        <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <h2 class="text-xl font-semibold text-slate-950">{{ $copy['orders'] }}</h2>
            <div class="flex flex-wrap gap-1 rounded-xl border border-slate-200 bg-white p-1">
                @foreach (['' => 'all', 'new' => 'new', 'preparing' => 'preparing', 'ready' => 'ready', 'served' => 'served', 'paid' => 'paid'] as $value => $label)
                    <a href="{{ route('restaurant.index', array_filter(['status' => $value])) }}" class="rounded-lg px-3 py-1.5 text-xs font-semibold {{ request('status', '') === $value ? 'bg-slate-950 text-white' : 'text-slate-600 hover:bg-slate-100' }}">{{ $copy[$label] }}</a>
                @endforeach
            </div>
        </div>

        <div class="grid gap-4 lg:grid-cols-2 2xl:grid-cols-3">
            @forelse ($orders as $order)
                <article class="panel overflow-hidden">
                    <header class="flex items-start justify-between gap-4 border-b border-slate-100 p-4">
                        <div>
                            <div class="flex items-center gap-2">
                                <h3 class="font-semibold text-slate-950">{{ $order->table?->name ?? 'Takeaway' }}</h3>
                                <span class="badge {{ $statusClass($order->status) }}">{{ $copy[$order->status] ?? $order->status }}</span>
                            </div>
                            <p class="mt-1 font-mono text-[11px] text-slate-400">{{ $order->number }} · {{ optional($order->opened_at)->format('H:i') }}</p>
                        </div>
                        <strong class="text-lg text-slate-950">{{ number_format((float) $order->total, 2, '.', ' ') }} ₼</strong>
                    </header>
                    <div class="divide-y divide-slate-100">
                        @foreach ($order->items as $item)
                            <div class="flex items-start justify-between gap-3 px-4 py-3 text-sm">
                                <div>
                                    <span class="font-medium text-slate-900">{{ number_format((float) $item->quantity, 0) }}× {{ $item->name }}</span>
                                    @if ($item->notes)<p class="mt-0.5 text-xs text-slate-500">{{ $item->notes }}</p>@endif
                                </div>
                                <span class="text-slate-500">{{ number_format((float) $item->total, 2, '.', ' ') }} ₼</span>
                            </div>
                        @endforeach
                    </div>
                    <footer class="flex flex-wrap items-center justify-between gap-2 border-t border-slate-100 bg-slate-50/70 p-4">
                        <span class="text-xs text-slate-500">{{ $order->guest_count }} {{ $copy['guests'] }} · {{ $order->items->count() }} {{ $copy['items'] }}</span>
                        <div class="flex flex-wrap gap-2">
                            @if (in_array($order->status, ['open', 'new'], true))
                                @if($canUseKitchen)<form method="POST" action="{{ route('restaurant.orders.status', $order) }}">@csrf @method('PATCH')<input type="hidden" name="status" value="preparing"><button class="btn btn-primary text-xs">{{ $copy['start_preparing'] }}</button></form>@endif
                                @if($canUseRegister)<form method="POST" action="{{ route('restaurant.orders.status', $order) }}">@csrf @method('PATCH')<input type="hidden" name="status" value="cancelled"><button class="btn text-xs text-rose-700">{{ $copy['cancel'] }}</button></form>@endif
                            @elseif ($order->status === 'preparing' && $canUseKitchen)
                                <form method="POST" action="{{ route('restaurant.orders.status', $order) }}">@csrf @method('PATCH')<input type="hidden" name="status" value="ready"><button class="btn btn-primary text-xs">{{ $copy['mark_ready'] }}</button></form>
                            @elseif ($order->status === 'ready' && $canUseRegister)
                                <form method="POST" action="{{ route('restaurant.orders.status', $order) }}">@csrf @method('PATCH')<input type="hidden" name="status" value="served"><button class="btn btn-primary text-xs">{{ $copy['mark_served'] }}</button></form>
                            @elseif ($order->status === 'served' && $canUseRegister)
                                @if ($warehouses->isNotEmpty())
                                    <form method="POST" action="{{ route('restaurant.orders.pay', $order) }}" class="grid w-full gap-2 sm:grid-cols-2">
                                        @csrf
                                        <label class="field text-xs">
                                            <span>{{ $copy['payment_method'] }}</span>
                                            <select name="payment_method" required>
                                                <option value="cash">{{ $copy['cash'] }}</option>
                                                <option value="card">{{ $copy['card'] }}</option>
                                                <option value="transfer">{{ $copy['transfer'] }}</option>
                                            </select>
                                        </label>
                                        <label class="field text-xs">
                                            <span>{{ $copy['amount_received'] }}</span>
                                            <input type="number" name="amount_received" min="0.01" step="0.01" value="{{ number_format((float) $order->total, 2, '.', '') }}" required>
                                        </label>
                                        <label class="field text-xs">
                                            <span>{{ $copy['warehouse'] }}</span>
                                            <select name="warehouse_id" required>
                                                @foreach ($warehouses as $warehouse)
                                                    <option value="{{ $warehouse->id }}">{{ $warehouse->name }}</option>
                                                @endforeach
                                            </select>
                                        </label>
                                        <label class="field text-xs">
                                            <span>{{ $copy['payment_reference'] }}</span>
                                            <input name="payment_reference" maxlength="160">
                                        </label>
                                        <button class="btn btn-primary text-xs sm:col-span-2" type="submit">{{ $copy['take_payment'] }}</button>
                                    </form>
                                @else
                                    <p class="w-full text-xs font-semibold text-rose-700">{{ $copy['no_warehouse'] }}</p>
                                @endif
                            @endif
                        </div>
                    </footer>
                </article>
            @empty
                <div class="panel col-span-full py-16 text-center text-sm text-slate-500">{{ $copy['no_orders'] }}</div>
            @endforelse
        </div>
    </section>

    @if($canManageTables)<dialog id="table-dialog" class="m-auto w-[min(92vw,460px)] rounded-3xl bg-white p-0 shadow-2xl backdrop:bg-slate-950/50">
        <form method="POST" action="{{ route('restaurant.tables.store') }}" class="p-6">
            @csrf
            <div class="mb-5 flex items-center justify-between"><h2 class="text-xl font-semibold text-slate-950">{{ $copy['new_table'] }}</h2><button type="button" class="rounded-lg p-2 text-slate-500 hover:bg-slate-100" data-dialog-close>×</button></div>
            <label class="mb-4 block"><span class="mb-1.5 block text-sm font-medium text-slate-700">{{ $copy['table_name'] }}</span><input class="field w-full" name="name" required maxlength="80"></label>
            <label class="mb-6 block"><span class="mb-1.5 block text-sm font-medium text-slate-700">{{ $copy['seat_count'] }}</span><input class="field w-full" type="number" name="seats" min="1" max="100" value="4" required></label>
            <button class="btn btn-primary w-full" type="submit">{{ $copy['create'] }}</button>
        </form>
    </dialog>@endif

    @if($canCreateOrders)<dialog id="order-dialog" class="m-auto max-h-[92vh] w-[min(94vw,720px)] overflow-y-auto rounded-3xl bg-white p-0 shadow-2xl backdrop:bg-slate-950/50">
        <form method="POST" action="{{ route('restaurant.orders.store') }}" class="p-6" id="restaurant-order-form">
            @csrf
            <div class="mb-5 flex items-center justify-between"><h2 class="text-xl font-semibold text-slate-950">{{ $copy['new_order'] }}</h2><button type="button" class="rounded-lg p-2 text-slate-500 hover:bg-slate-100" data-dialog-close>×</button></div>
            <div class="grid gap-4 sm:grid-cols-2">
                <label><span class="mb-1.5 block text-sm font-medium text-slate-700">{{ $copy['select_table'] }}</span><select class="field w-full" name="table_id" required><option value="">—</option>@foreach ($tables as $table)<option value="{{ $table->id }}">{{ $table->name }} · {{ $table->seats }} {{ $copy['seats'] }}</option>@endforeach</select></label>
                <label><span class="mb-1.5 block text-sm font-medium text-slate-700">{{ $copy['guest_count'] }}</span><input class="field w-full" type="number" name="guest_count" min="1" max="100" value="1"></label>
            </div>
            <label class="mt-4 block"><span class="mb-1.5 block text-sm font-medium text-slate-700">{{ $copy['notes'] }}</span><textarea class="field min-h-20 w-full" name="notes" maxlength="1000"></textarea></label>

            <div class="mt-5 space-y-3" id="order-lines"></div>
            <button class="btn mt-3 w-full border-dashed" type="button" id="add-order-line">+ {{ $copy['add_item'] }}</button>
            @if ($menuItems->isEmpty())<p class="mt-3 text-sm text-amber-700">{{ $copy['no_menu'] }}</p>@endif
            <button class="btn btn-primary mt-6 w-full" type="submit" @disabled($menuItems->isEmpty())>{{ $copy['send_kitchen'] }}</button>
        </form>
    </dialog>

    <template id="order-line-template">
        <div class="order-line grid grid-cols-[1fr_100px_auto] gap-2 rounded-2xl border border-slate-200 bg-slate-50 p-3">
            <label><span class="mb-1 block text-xs font-medium text-slate-500">{{ $copy['menu_item'] }}</span><select class="field w-full" name="items[__INDEX__][product_id]" required><option value="">—</option>@foreach ($menuItems as $product)<option value="{{ $product->id }}">{{ $productName($product) }} · {{ number_format((float) $product->sale_price, 2, '.', ' ') }} ₼</option>@endforeach</select></label>
            <label><span class="mb-1 block text-xs font-medium text-slate-500">{{ $copy['quantity'] }}</span><input class="field w-full" type="number" min="0.01" step="0.01" max="999" value="1" name="items[__INDEX__][quantity]" required></label>
            <button type="button" class="remove-order-line mt-5 h-10 w-10 rounded-xl text-rose-600 hover:bg-rose-100" aria-label="{{ $copy['cancel'] }}">×</button>
        </div>
    </template>@endif
@endsection

@push('scripts')
    <script>
        document.querySelectorAll('[data-dialog-open]').forEach((button) => {
            button.addEventListener('click', () => document.getElementById(button.dataset.dialogOpen)?.showModal());
        });
        document.querySelectorAll('[data-dialog-close]').forEach((button) => {
            button.addEventListener('click', () => button.closest('dialog')?.close());
        });

        const lines = document.getElementById('order-lines');
        const template = document.getElementById('order-line-template');
        const addLineButton = document.getElementById('add-order-line');
        let lineIndex = 0;

        const addLine = () => {
            if (!lines || !template) return;
            const fragment = template.content.cloneNode(true);
            fragment.querySelectorAll('[name]').forEach((element) => {
                element.name = element.name.replace('__INDEX__', String(lineIndex));
            });
            fragment.querySelector('.remove-order-line')?.addEventListener('click', (event) => {
                if (lines.children.length > 1) event.currentTarget.closest('.order-line')?.remove();
            });
            lines.appendChild(fragment);
            lineIndex += 1;
        };

        addLineButton?.addEventListener('click', addLine);
        addLine();
    </script>
@endpush
