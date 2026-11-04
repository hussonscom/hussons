<?php

namespace App\Http\Controllers;

use App\Models\InventoryLot;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Stock;
use App\Services\TenantContext;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(Request $request, TenantContext $context)
    {
        $organization = $context->organization($request);
        $branch = $context->branch($request);

        $todaySales = Sale::query()
            ->where('organization_id', $organization->id)
            ->where('branch_id', $branch->id)
            ->where('status', 'completed')
            ->whereDate('sold_at', today());

        $lowStock = Stock::query()
            ->with(['product', 'warehouse'])
            ->where('organization_id', $organization->id)
            ->whereHas('warehouse', fn ($query) => $query->where('branch_id', $branch->id))
            ->whereHas('product', fn ($query) => $query->where('is_active', true))
            ->get()
            ->filter(fn ($stock) => (float) $stock->quantity <= (float) $stock->product->min_stock)
            ->sortBy('quantity')
            ->take(8);

        $expiringLots = InventoryLot::query()
            ->with(['product', 'warehouse'])
            ->where('organization_id', $organization->id)
            ->whereHas('warehouse', fn ($query) => $query->where('branch_id', $branch->id))
            ->whereIn('status', ['active', 'quarantined'])
            ->where('quantity_available', '>', 0)
            ->whereBetween('expiry_date', [today(), today()->addDays(45)])
            ->orderBy('expiry_date')
            ->limit(8)
            ->get();

        $recentSales = Sale::query()
            ->with(['customer', 'user'])
            ->where('organization_id', $organization->id)
            ->where('branch_id', $branch->id)
            ->latest('sold_at')
            ->limit(7)
            ->get();

        $topProducts = SaleItem::query()
            ->selectRaw('product_id, name, SUM(quantity) as sold_quantity, SUM(total) as revenue')
            ->whereHas('sale', fn ($query) => $query
                ->where('organization_id', $organization->id)
                ->where('branch_id', $branch->id)
                ->where('status', 'completed')
                ->where('sold_at', '>=', now()->subDays(30)))
            ->groupBy('product_id', 'name')
            ->orderByDesc('revenue')
            ->limit(5)
            ->get();

        return view('dashboard', [
            'organization' => $organization,
            'branch' => $branch,
            'metrics' => [
                'today_revenue' => (clone $todaySales)->sum('total'),
                'today_sales' => (clone $todaySales)->count(),
                'active_products' => Product::query()->where('organization_id', $organization->id)->where('is_active', true)->count(),
                'stock_units' => Stock::query()
                    ->where('organization_id', $organization->id)
                    ->whereHas('warehouse', fn ($query) => $query->where('branch_id', $branch->id))
                    ->sum('quantity'),
            ],
            'lowStock' => $lowStock,
            'expiringLots' => $expiringLots,
            'recentSales' => $recentSales,
            'topProducts' => $topProducts,
        ]);
    }
}
