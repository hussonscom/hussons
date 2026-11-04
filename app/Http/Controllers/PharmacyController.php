<?php

namespace App\Http\Controllers;

use App\Models\InventoryLot;
use App\Models\Organization;
use App\Models\Prescription;
use App\Models\Product;
use App\Models\Warehouse;
use App\Services\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PharmacyController extends Controller
{
    public function index(Request $request, TenantContext $context): View
    {
        $org = $context->organization($request);
        $branch = $context->branch($request);
        $warehouseIds = Warehouse::query()
            ->where('organization_id', $org->id)
            ->where('branch_id', $branch->id)
            ->pluck('id');
        $today = CarbonImmutable::today();
        $warningDays = min(365, max(1, $request->integer('warning_days', 60)));
        $warningDate = $today->addDays($warningDays);

        $lotsQuery = InventoryLot::query()
            ->where('organization_id', $org->id)
            ->whereIn('warehouse_id', $warehouseIds)
            ->with(['product', 'warehouse'])
            ->where('quantity_available', '>', 0)
            ->when($request->filled('search'), function (Builder $query) use ($request): void {
                $search = trim((string) $request->string('search'));
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('batch_number', 'like', "%{$search}%")
                        ->orWhereHas('product', function (Builder $product) use ($search): void {
                            $product->where('name_az', 'like', "%{$search}%")
                                ->orWhere('name_ru', 'like', "%{$search}%")
                                ->orWhere('name_en', 'like', "%{$search}%")
                                ->orWhere('barcode', 'like', "%{$search}%");
                        });
                });
            })
            ->when($request->string('status')->toString() === 'expired', fn (Builder $query) => $query->whereDate('expiry_date', '<', $today))
            ->when($request->string('status')->toString() === 'expiring', fn (Builder $query) => $query
                ->whereDate('expiry_date', '>=', $today)
                ->whereDate('expiry_date', '<=', $warningDate))
            ->when($request->string('status')->toString() === 'active', fn (Builder $query) => $query->whereDate('expiry_date', '>', $warningDate));

        $lots = (clone $lotsQuery)
            ->orderBy('expiry_date')
            ->orderBy('batch_number')
            ->paginate(24)
            ->withQueryString();

        $baseLots = InventoryLot::query()
            ->where('organization_id', $org->id)
            ->whereIn('warehouse_id', $warehouseIds)
            ->where('quantity_available', '>', 0);

        $stats = [
            'expired' => (clone $baseLots)->whereDate('expiry_date', '<', $today)->count(),
            'expiring' => (clone $baseLots)
                ->whereDate('expiry_date', '>=', $today)
                ->whereDate('expiry_date', '<=', $warningDate)
                ->count(),
            'available' => (clone $baseLots)->whereDate('expiry_date', '>=', $today)->sum('quantity_available'),
        ];

        $fefoLotIds = InventoryLot::query()
            ->where('organization_id', $org->id)
            ->whereIn('warehouse_id', $warehouseIds)
            ->where('quantity_available', '>', 0)
            ->whereDate('expiry_date', '>=', $today)
            ->orderBy('expiry_date')
            ->get(['id', 'product_id'])
            ->unique('product_id')
            ->pluck('id')
            ->all();

        $prescriptionProducts = Product::query()
            ->where('organization_id', $org->id)
            ->where('type', 'drug')
            ->get()
            ->filter(fn (Product $product): bool => (bool) data_get($product->metadata, 'prescription_required'))
            ->sortBy(fn (Product $product): string => mb_strtolower($product->name_az ?? $product->name_en ?? ''))
            ->values();

        $prescriptions = Prescription::query()
            ->where('organization_id', $org->id)
            ->where('branch_id', $branch->id)
            ->with(['sale', 'customer', 'verifier'])
            ->latest('issued_at')
            ->paginate(10, ['*'], 'prescriptions_page')
            ->withQueryString();

        return view('pharmacy.index', compact(
            'org',
            'lots',
            'stats',
            'today',
            'warningDays',
            'fefoLotIds',
            'prescriptionProducts',
            'prescriptions',
        ));
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
}
