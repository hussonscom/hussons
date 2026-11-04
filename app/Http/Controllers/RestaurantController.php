<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Organization;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\RestaurantOrder;
use App\Models\RestaurantOrderItem;
use App\Models\RestaurantTable;
use App\Models\Sale;
use App\Models\Warehouse;
use App\Services\AuditLogger;
use App\Services\Money;
use App\Services\SaleService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Ramsey\Uuid\Uuid;

class RestaurantController extends Controller
{
    private const ACTIVE_STATUSES = ['open', 'new', 'preparing', 'ready', 'served'];

    private const TRANSITIONS = [
        'open' => ['preparing', 'cancelled'],
        'new' => ['preparing', 'cancelled'],
        'preparing' => ['ready', 'cancelled'],
        'ready' => ['served'],
        'served' => [],
        'paid' => [],
        'cancelled' => [],
    ];

    private const ITEM_TRANSITIONS = [
        'new' => ['preparing', 'cancelled'],
        'preparing' => ['ready', 'cancelled'],
        'ready' => ['served', 'cancelled'],
        'served' => [],
        'cancelled' => [],
    ];

    public function index(Request $request): View
    {
        $org = $this->currentOrganization($request);
        $branch = $this->currentBranch($request, $org);

        $tables = RestaurantTable::query()
            ->where('organization_id', $org->id)
            ->where('branch_id', $branch->id)
            ->where('is_active', true)
            ->withCount(['orders as active_orders_count' => fn (Builder $query) => $query->whereIn('status', self::ACTIVE_STATUSES)])
            ->orderBy('name')
            ->get();

        $orders = RestaurantOrder::query()
            ->where('organization_id', $org->id)
            ->where('branch_id', $branch->id)
            ->with(['table', 'items.product', 'user'])
            ->when(
                $request->string('status')->toString(),
                fn (Builder $query, string $status) => $status === 'new'
                    ? $query->whereIn('status', ['open', 'new'])
                    : $query->where('status', $status),
                fn (Builder $query) => $query->where(function (Builder $query): void {
                    $query->whereIn('status', self::ACTIVE_STATUSES)
                        ->orWhere(fn (Builder $recent) => $recent
                            ->whereIn('status', ['paid', 'cancelled'])
                            ->where('updated_at', '>=', now()->subHours(12)));
                }),
            )
            ->latest('opened_at')
            ->get();

        $menuItems = Product::query()
            ->where('organization_id', $org->id)
            ->where('is_active', true)
            ->where('type', 'food')
            ->orderBy('name_az')
            ->get();

        $warehouses = Warehouse::query()
            ->where('organization_id', $org->id)
            ->where('branch_id', $branch->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $summary = [
            'open' => $orders->whereIn('status', self::ACTIVE_STATUSES)->count(),
            'ready' => $orders->where('status', 'ready')->count(),
            'occupied' => $tables->filter(fn (RestaurantTable $table): bool => $table->active_orders_count > 0)->count(),
            'revenue' => RestaurantOrder::query()
                ->where('organization_id', $org->id)
                ->where('branch_id', $branch->id)
                ->where('status', 'paid')
                ->whereDate('closed_at', today())
                ->sum('total'),
        ];

        return view('restaurant.index', compact('org', 'branch', 'tables', 'orders', 'menuItems', 'warehouses', 'summary'));
    }

    public function storeTable(Request $request): RedirectResponse
    {
        $this->requireRole($request, ['owner', 'admin', 'branch_manager']);
        $org = $this->currentOrganization($request);
        $branch = $this->currentBranch($request, $org);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'seats' => ['required', 'integer', 'min:1', 'max:100'],
        ]);

        $duplicate = RestaurantTable::query()
            ->where('organization_id', $org->id)
            ->where('branch_id', $branch->id)
            ->where('name', $validated['name'])
            ->exists();

        if ($duplicate) {
            return back()->withErrors(['name' => __('A table with this name already exists.')])->withInput();
        }

        $table = RestaurantTable::create([
            'organization_id' => $org->id,
            'branch_id' => $branch->id,
            'code' => 'T-'.strtoupper(Str::random(6)),
            'name' => $validated['name'],
            'seats' => $validated['seats'],
            'status' => 'available',
            'is_active' => true,
        ]);

        app(AuditLogger::class)->record('restaurant.table.created', $table, null, $table->toArray());

        return back()->with('success', __('Table created.'));
    }

    public function storeOrder(Request $request): RedirectResponse
    {
        $this->requireRole($request, ['owner', 'admin', 'branch_manager', 'cashier']);
        $org = $this->currentOrganization($request);
        $branch = $this->currentBranch($request, $org);

        $validated = $request->validate([
            'table_id' => [
                'required',
                Rule::exists('restaurant_tables', 'id')->where(fn ($query) => $query
                    ->where('organization_id', $org->id)
                    ->where('branch_id', $branch->id)
                    ->where('is_active', true)),
            ],
            'guest_count' => ['nullable', 'integer', 'min:1', 'max:100'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*.product_id' => ['required', 'integer'],
            'items.*.variant_id' => ['nullable', 'integer'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0', 'max:999'],
            'items.*.notes' => ['nullable', 'string', 'max:500'],
        ]);

        $order = DB::transaction(function () use ($validated, $org, $branch, $request): RestaurantOrder {
            $table = RestaurantTable::query()
                ->where('organization_id', $org->id)
                ->where('branch_id', $branch->id)
                ->lockForUpdate()
                ->findOrFail($validated['table_id']);

            $preparedItems = collect($validated['items'])->map(function (array $item) use ($org): array {
                $product = Product::query()
                    ->where('organization_id', $org->id)
                    ->where('type', 'food')
                    ->findOrFail($item['product_id']);

                $variant = null;
                if (! empty($item['variant_id'])) {
                    $variant = ProductVariant::query()
                        ->where('organization_id', $org->id)
                        ->where('product_id', $product->id)
                        ->findOrFail($item['variant_id']);
                }

                $unitPriceCents = Money::toCents($variant?->sale_price ?? $product->sale_price);
                $quantityMillis = Money::quantityToMillis($item['quantity']);
                $subtotalCents = Money::lineTotal($unitPriceCents, $quantityMillis);
                $taxCents = Money::tax($subtotalCents, $product->tax_rate);

                return [
                    'product' => $product,
                    'variant' => $variant,
                    'quantity' => $quantityMillis / 1000,
                    'unit_price' => Money::fromCents($unitPriceCents),
                    'subtotal_cents' => $subtotalCents,
                    'tax_cents' => $taxCents,
                    'total' => Money::fromCents($subtotalCents + $taxCents),
                    'notes' => $item['notes'] ?? null,
                ];
            });

            $subtotal = Money::fromCents((int) $preparedItems->sum('subtotal_cents'));
            $tax = Money::fromCents((int) $preparedItems->sum('tax_cents'));
            $total = Money::fromCents(
                (int) $preparedItems->sum('subtotal_cents') + (int) $preparedItems->sum('tax_cents'),
            );

            $order = RestaurantOrder::create([
                'organization_id' => $org->id,
                'branch_id' => $branch->id,
                'restaurant_table_id' => $table->id,
                'user_id' => $request->user()->id,
                'number' => 'FO-'.now()->format('ymd').'-'.strtoupper(Str::random(5)),
                'status' => 'new',
                'guest_count' => $validated['guest_count'] ?? 1,
                'subtotal' => $subtotal,
                'discount' => 0,
                'tax' => $tax,
                'total' => $total,
                'notes' => $validated['notes'] ?? null,
                'opened_at' => now(),
            ]);

            foreach ($preparedItems as $item) {
                $order->items()->create([
                    'product_id' => $item['product']->id,
                    'product_variant_id' => $item['variant']?->id,
                    'name' => $this->localizedProductName($item['product']),
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'discount' => 0,
                    'tax' => Money::fromCents($item['tax_cents']),
                    'total' => $item['total'],
                    'notes' => $item['notes'],
                    'status' => 'new',
                ]);
            }

            $table->update(['status' => 'occupied']);

            return $order;
        });

        app(AuditLogger::class)->record('restaurant.order.created', $order, null, $order->load('items')->toArray());

        return back()->with('success', __('Order :number created.', ['number' => $order->number]));
    }

    public function updateStatus(Request $request, RestaurantOrder $order): RedirectResponse
    {
        $org = $this->currentOrganization($request);
        $branch = $this->currentBranch($request, $org);

        abort_unless($order->organization_id === $org->id && $order->branch_id === $branch->id, 404);

        $validated = $request->validate([
            'status' => ['required', Rule::in(['new', 'preparing', 'ready', 'served', 'cancelled'])],
        ]);

        $roles = in_array($validated['status'], ['preparing', 'ready'], true)
            ? ['owner', 'admin', 'branch_manager', 'kitchen']
            : ['owner', 'admin', 'branch_manager', 'cashier'];
        $this->requireRole($request, $roles);

        DB::transaction(function () use ($order, $validated, $org, $branch): void {
            $lockedOrder = RestaurantOrder::query()
                ->where('organization_id', $org->id)
                ->where('branch_id', $branch->id)
                ->whereKey($order->id)
                ->lockForUpdate()
                ->firstOrFail();
            $status = $validated['status'];
            $allowed = self::TRANSITIONS[$lockedOrder->status] ?? [];
            abort_unless(in_array($status, $allowed, true), 422, 'Invalid order status transition.');
            $before = $lockedOrder->toArray();
            $attributes = ['status' => $status];

            if ($status === 'cancelled') {
                $attributes['closed_at'] = now();
                $lockedOrder->items()->whereNotIn('status', ['cancelled'])->update(['status' => 'cancelled']);
            }

            $lockedOrder->update($attributes);

            if (in_array($status, ['preparing', 'ready', 'served'], true)) {
                $lockedOrder->items()->whereNotIn('status', ['cancelled'])->update(['status' => $status]);
            }

            if ($status === 'cancelled' && $lockedOrder->restaurant_table_id) {
                $this->releaseTableIfUnused($lockedOrder, $org, $branch);
            }

            app(AuditLogger::class)->record(
                'restaurant.order.status_changed',
                $lockedOrder,
                $before,
                $lockedOrder->fresh()->toArray(),
            );
        });

        return back()->with('success', __('Order status updated.'));
    }

    public function pay(
        Request $request,
        RestaurantOrder $order,
        SaleService $sales,
    ): RedirectResponse {
        $this->requireRole($request, ['owner', 'admin', 'branch_manager', 'cashier']);
        $org = $this->currentOrganization($request);
        $branch = $this->currentBranch($request, $org);

        abort_unless($order->organization_id === $org->id && $order->branch_id === $branch->id, 404);

        $validated = $request->validate([
            'warehouse_id' => [
                'required',
                Rule::exists('warehouses', 'id')->where(fn ($query) => $query
                    ->where('organization_id', $org->id)
                    ->where('branch_id', $branch->id)
                    ->where('is_active', true)),
            ],
            'payment_method' => ['required', Rule::in(['cash', 'card', 'transfer'])],
            'amount_received' => ['required', 'numeric', 'gt:0', 'max:999999999.99'],
            'payment_reference' => ['nullable', 'string', 'max:160'],
        ]);
        $idempotencyKey = Uuid::uuid5(
            Uuid::NAMESPACE_URL,
            "https://hussons.com/idempotency/restaurant/{$org->id}/{$order->id}",
        )->toString();

        $sale = DB::transaction(function () use ($request, $order, $org, $branch, $validated, $sales, $idempotencyKey) {
            $lockedOrder = RestaurantOrder::query()
                ->where('organization_id', $org->id)
                ->where('branch_id', $branch->id)
                ->with('items')
                ->lockForUpdate()
                ->findOrFail($order->id);

            $isReplay = $lockedOrder->sale_id !== null;
            if ((! $isReplay && $lockedOrder->status !== 'served')
                || ($isReplay && $lockedOrder->status !== 'paid')) {
                throw ValidationException::withMessages([
                    'order' => __('This order is not ready for payment or has already been paid.'),
                ]);
            }

            $linkedSale = null;
            if ($isReplay) {
                $linkedSale = Sale::query()
                    ->where('organization_id', $org->id)
                    ->where('branch_id', $branch->id)
                    ->findOrFail($lockedOrder->sale_id);

                // Sales created before operation keys existed are still protected by the order lock/link.
                if ($linkedSale->idempotency_key !== $idempotencyKey) {
                    return $linkedSale;
                }
            }

            $warehouse = Warehouse::query()
                ->where('organization_id', $org->id)
                ->where('branch_id', $branch->id)
                ->where('is_active', true)
                ->lockForUpdate()
                ->findOrFail($validated['warehouse_id']);

            $activeItems = $lockedOrder->items->where('status', '!=', 'cancelled');
            if ($activeItems->isEmpty()) {
                throw ValidationException::withMessages([
                    'order' => __('An empty order cannot be paid.'),
                ]);
            }

            $sale = $sales->checkout(
                $org->id,
                $branch,
                $warehouse,
                $request->user(),
                [
                    'idempotency_key' => $idempotencyKey,
                    'notes' => 'Restaurant order '.$lockedOrder->number,
                    'items' => $activeItems->map(fn (RestaurantOrderItem $item): array => [
                        'product_id' => $item->product_id,
                        'product_variant_id' => $item->product_variant_id,
                        'quantity' => $item->quantity,
                        'unit_price' => $item->unit_price,
                        'discount' => $item->discount,
                    ])->values()->all(),
                    'payments' => [[
                        'method' => $validated['payment_method'] === 'transfer' ? 'bank' : $validated['payment_method'],
                        'amount' => $validated['amount_received'],
                        'reference' => $validated['payment_reference'] ?? null,
                    ]],
                ],
                // Menu prices are captured when the order is opened and stay fixed through payment.
                'branch_manager',
            );

            if ($isReplay) {
                if ((int) $sale->id !== (int) $linkedSale->id) {
                    throw new \LogicException('Restaurant payment is linked to a different sale.');
                }

                return $sale;
            }

            $before = $lockedOrder->toArray();
            $lockedOrder->update([
                'sale_id' => $sale->id,
                'status' => 'paid',
                'closed_at' => now(),
            ]);
            $lockedOrder->items()->whereNotIn('status', ['cancelled'])->update(['status' => 'served']);
            $this->releaseTableIfUnused($lockedOrder, $org, $branch);
            app(AuditLogger::class)->record(
                'restaurant.order.paid',
                $lockedOrder,
                $before,
                $lockedOrder->fresh()->toArray(),
            );

            return $sale;
        }, 3);

        return redirect()->route('sales.show', $sale)->with('success', __('Payment completed and receipt issued.'));
    }

    public function updateItemStatus(Request $request, RestaurantOrderItem $item): RedirectResponse
    {
        $this->requireRole($request, ['owner', 'admin', 'branch_manager', 'kitchen']);
        $org = $this->currentOrganization($request);
        $branch = $this->currentBranch($request, $org);
        $item->loadMissing('order');

        abort_unless($item->order?->organization_id === $org->id && $item->order?->branch_id === $branch->id, 404);

        $validated = $request->validate([
            'status' => ['required', Rule::in(['new', 'preparing', 'ready', 'served', 'cancelled'])],
        ]);

        DB::transaction(function () use ($item, $validated, $org, $branch): void {
            $lockedItem = RestaurantOrderItem::query()
                ->whereKey($item->id)
                ->whereHas('order', fn (Builder $query) => $query
                    ->where('organization_id', $org->id)
                    ->where('branch_id', $branch->id))
                ->with('order')
                ->lockForUpdate()
                ->firstOrFail();

            abort_unless(! in_array($lockedItem->order->status, ['paid', 'cancelled'], true), 422, 'Closed orders cannot be changed.');
            $allowed = self::ITEM_TRANSITIONS[$lockedItem->status] ?? [];
            abort_unless(in_array($validated['status'], $allowed, true), 422, 'Invalid item status transition.');

            $before = $lockedItem->toArray();
            $lockedItem->update(['status' => $validated['status']]);
            $this->recalculateOrderTotals($lockedItem->order);
            app(AuditLogger::class)->record(
                'restaurant.order_item.status_changed',
                $lockedItem,
                $before,
                $lockedItem->fresh()->toArray(),
            );
        });

        return back()->with('success', __('Item status updated.'));
    }

    private function currentOrganization(Request $request): Organization
    {
        $org = $request->attributes->get('currentOrganization')
            ?? $request->attributes->get('current_org')
            ?? $request->attributes->get('organization');

        if (! $org && app()->bound('currentOrganization')) {
            $org = app('currentOrganization');
        }

        if (! $org && $request->user()) {
            $org = $request->user()->organizations()->first();
        }

        abort_unless($org instanceof Organization, 403, 'Organization context is required.');

        return $org;
    }

    private function currentBranch(Request $request, Organization $org): Branch
    {
        $branch = $request->attributes->get('currentBranch')
            ?? $request->attributes->get('current_branch')
            ?? $request->attributes->get('branch');

        if (! $branch && app()->bound('currentBranch')) {
            $branch = app('currentBranch');
        }

        if (! $branch) {
            $branch = $org->branches()->where('is_active', true)->first();
        }

        abort_unless($branch instanceof Branch && $branch->organization_id === $org->id, 403, 'Branch context is required.');

        return $branch;
    }

    private function localizedProductName(Product $product): string
    {
        $locale = app()->getLocale();
        $field = in_array($locale, ['az', 'ru', 'en'], true) ? "name_{$locale}" : 'name_az';

        return $product->{$field} ?: $product->name_az ?: $product->name_en ?: $product->sku;
    }

    private function releaseTableIfUnused(RestaurantOrder $order, Organization $org, Branch $branch): void
    {
        if (! $order->restaurant_table_id) {
            return;
        }

        $hasActiveOrders = RestaurantOrder::query()
            ->where('organization_id', $org->id)
            ->where('branch_id', $branch->id)
            ->where('restaurant_table_id', $order->restaurant_table_id)
            ->where('id', '!=', $order->id)
            ->whereIn('status', self::ACTIVE_STATUSES)
            ->exists();

        if (! $hasActiveOrders) {
            RestaurantTable::query()
                ->where('organization_id', $org->id)
                ->where('branch_id', $branch->id)
                ->whereKey($order->restaurant_table_id)
                ->update(['status' => 'available']);
        }
    }

    /** @param list<string> $roles */
    private function requireRole(Request $request, array $roles): void
    {
        $membership = $request->attributes->get('membership');
        abort_unless($membership && in_array($membership->role, $roles, true), 403);
    }

    private function recalculateOrderTotals(RestaurantOrder $order): void
    {
        $activeItems = $order->items()->where('status', '!=', 'cancelled')->get();
        $subtotalCents = $activeItems->sum(fn (RestaurantOrderItem $item): int => Money::toCents($item->total) - Money::toCents($item->tax) + Money::toCents($item->discount));
        $discountCents = $activeItems->sum(fn (RestaurantOrderItem $item): int => Money::toCents($item->discount));
        $taxCents = $activeItems->sum(fn (RestaurantOrderItem $item): int => Money::toCents($item->tax));
        $attributes = [
            'subtotal' => Money::fromCents((int) $subtotalCents),
            'discount' => Money::fromCents((int) $discountCents),
            'tax' => Money::fromCents((int) $taxCents),
            'total' => Money::fromCents((int) $subtotalCents - (int) $discountCents + (int) $taxCents),
        ];

        if ($activeItems->isEmpty()) {
            $attributes['status'] = 'cancelled';
            $attributes['closed_at'] = now();
        }

        $order->update($attributes);
        if ($activeItems->isEmpty()) {
            $this->releaseTableIfUnused($order, $order->organization, $order->branch);
        }
    }
}
