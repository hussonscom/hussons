<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSaleRequest;
use App\Models\Customer;
use App\Models\InventoryLot;
use App\Models\InventoryUnit;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Warehouse;
use App\Services\SaleService;
use App\Services\TenantContext;
use Illuminate\Http\Request;

class SalesController extends Controller
{
    public function index(Request $request, TenantContext $context)
    {
        $organization = $context->organization($request);
        $branch = $context->branch($request);
        $sales = Sale::query()
            ->with(['customer', 'user', 'payments'])
            ->where('organization_id', $organization->id)
            ->where('branch_id', $branch->id)
            ->when($request->filled('q'), fn ($query) => $query->where('number', 'like', '%'.$request->string('q')->trim().'%'))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->latest('sold_at')
            ->paginate(25)
            ->withQueryString();

        return view('sales.index', compact('sales'));
    }

    public function create(Request $request, TenantContext $context)
    {
        $organization = $context->organization($request);
        $branch = $context->branch($request);
        $warehouseIds = Warehouse::query()->where('organization_id', $organization->id)->where('branch_id', $branch->id)->pluck('id');

        $products = Product::query()
            ->with(['variants', 'stocks' => fn ($query) => $query->whereIn('warehouse_id', $warehouseIds)])
            ->where('organization_id', $organization->id)
            ->where('is_active', true)
            ->orderBy('name_az')
            ->get();

        return view('sales.pos', [
            'products' => $products,
            'warehouses' => Warehouse::query()->whereIn('id', $warehouseIds)->where('is_active', true)->orderBy('name')->get(),
            'customers' => Customer::query()->where('organization_id', $organization->id)->where('is_active', true)->orderBy('name')->get(),
            'serialUnits' => InventoryUnit::query()->where('organization_id', $organization->id)->whereIn('warehouse_id', $warehouseIds)->where('status', 'in_stock')->get()->groupBy('product_id'),
            'lots' => InventoryLot::query()->where('organization_id', $organization->id)->whereIn('warehouse_id', $warehouseIds)->where('status', 'active')->whereDate('expiry_date', '>=', today())->orderBy('expiry_date')->get()->groupBy('product_id'),
        ]);
    }

    public function store(StoreSaleRequest $request, TenantContext $context, SaleService $sales)
    {
        $organization = $context->organization($request);
        $branch = $context->branch($request);
        abort_unless((int) $request->validated('branch_id') === (int) $branch->id, 404);
        $warehouse = $context->warehouse($request, (int) $request->validated('warehouse_id'));
        $membership = $request->attributes->get('membership');

        $sale = $sales->checkout(
            $organization->id,
            $branch,
            $warehouse,
            $request->user(),
            $request->validated(),
            $membership?->role,
        );

        return redirect()->route('sales.show', $sale)->with('status', __('Sale completed.'));
    }

    public function show(Request $request, Sale $sale, TenantContext $context)
    {
        $organization = $context->organization($request);
        $branch = $context->branch($request);
        abort_unless(
            (int) $sale->organization_id === (int) $organization->id
            && (int) $sale->branch_id === (int) $branch->id,
            404,
        );

        return view('sales.show', [
            'sale' => $sale->load(['items.product', 'items.inventoryUnit', 'items.inventoryLot', 'payments', 'customer', 'user', 'branch', 'fiscalReceipts']),
        ]);
    }

    public function refund(Request $request, Sale $sale, TenantContext $context, SaleService $sales)
    {
        $request->validate(['reason' => ['required', 'string', 'max:500']]);
        $organization = $context->organization($request);
        $branch = $context->branch($request);
        abort_unless(
            (int) $sale->organization_id === (int) $organization->id
            && (int) $sale->branch_id === (int) $branch->id,
            404,
        );
        $sales->refund($organization->id, $sale, $request->user(), $request->string('reason')->toString());

        return back()->with('status', __('Sale refunded and stock restored.'));
    }
}
