<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReceiveStockRequest;
use App\Models\InventoryLot;
use App\Models\InventoryUnit;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Stock;
use App\Models\Supplier;
use App\Models\Warehouse;
use App\Services\InventoryService;
use App\Services\TenantContext;
use Illuminate\Http\Request;

class InventoryController extends Controller
{
    public function index(Request $request, TenantContext $context)
    {
        $organization = $context->organization($request);
        $branch = $context->branch($request);
        $warehouseIds = Warehouse::query()->where('organization_id', $organization->id)->where('branch_id', $branch->id)->pluck('id');

        $stocks = Stock::query()
            ->with(['product.category', 'variant', 'warehouse'])
            ->where('organization_id', $organization->id)
            ->whereIn('warehouse_id', $warehouseIds)
            ->when($request->filled('q'), fn ($query) => $query->whereHas('product', fn ($productQuery) => $productQuery
                ->where('sku', 'like', '%'.$request->string('q')->trim().'%')
                ->orWhere('name_az', 'like', '%'.$request->string('q')->trim().'%')))
            ->orderByDesc('quantity')
            ->paginate(30)
            ->withQueryString();

        $expiringLots = InventoryLot::query()
            ->with('product')
            ->where('organization_id', $organization->id)
            ->whereIn('warehouse_id', $warehouseIds)
            ->where('quantity_available', '>', 0)
            ->whereDate('expiry_date', '<=', today()->addDays(60))
            ->orderBy('expiry_date')
            ->limit(20)
            ->get();

        return view('inventory.index', compact('stocks', 'expiringLots'));
    }

    public function create(Request $request, TenantContext $context)
    {
        $organization = $context->organization($request);
        $branch = $context->branch($request);

        return view('inventory.receive', [
            'products' => Product::query()->with('variants')->where('organization_id', $organization->id)->where('is_active', true)->orderBy('name_az')->get(),
            'warehouses' => Warehouse::query()->where('organization_id', $organization->id)->where('branch_id', $branch->id)->where('is_active', true)->orderBy('name')->get(),
            'suppliers' => Supplier::query()->where('organization_id', $organization->id)->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function store(
        ReceiveStockRequest $request,
        TenantContext $context,
        InventoryService $inventory,
    ) {
        $organization = $context->organization($request);
        $branch = $context->branch($request);
        $warehouse = $context->warehouse($request, (int) $request->validated('warehouse_id'));
        $purchase = $inventory->receive($organization->id, $branch->id, $warehouse, $request->user(), $request->validated());

        return redirect()->route('inventory.purchase', $purchase)->with('status', __('Stock receipt completed.'));
    }

    public function show(Request $request, Purchase $purchase, TenantContext $context)
    {
        $organization = $context->organization($request);
        $branch = $context->branch($request);
        abort_unless(
            (int) $purchase->organization_id === (int) $organization->id
            && (int) $purchase->branch_id === (int) $branch->id,
            404,
        );

        return view('inventory.purchase', [
            'purchase' => $purchase->load(['items.product', 'supplier', 'warehouse', 'user']),
        ]);
    }

    public function serials(Request $request, TenantContext $context)
    {
        $organization = $context->organization($request);
        $branch = $context->branch($request);
        $warehouseIds = Warehouse::query()
            ->where('organization_id', $organization->id)
            ->where('branch_id', $branch->id)
            ->pluck('id');
        $units = InventoryUnit::query()
            ->with(['product', 'variant', 'warehouse'])
            ->where('organization_id', $organization->id)
            ->whereIn('warehouse_id', $warehouseIds)
            ->when($request->filled('q'), fn ($query) => $query->where(fn ($inner) => $inner
                ->where('serial_number', 'like', '%'.$request->string('q')->trim().'%')
                ->orWhere('imei1', 'like', '%'.$request->string('q')->trim().'%')
                ->orWhere('imei2', 'like', '%'.$request->string('q')->trim().'%')))
            ->latest('id')
            ->paginate(30)
            ->withQueryString();

        return view('inventory.serials', compact('units'));
    }
}
