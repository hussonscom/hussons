<?php

namespace App\Http\Controllers;

use App\Models\InventoryMovement;
use App\Models\InventoryUnit;
use App\Models\Stock;
use App\Services\AuditLogger;
use App\Services\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

final class InventoryReturnController extends Controller
{
    public function update(
        Request $request,
        InventoryUnit $unit,
        TenantContext $context,
        AuditLogger $audit,
    ): RedirectResponse {
        $organization = $context->organization($request);
        $branch = $context->branch($request);
        $validated = $request->validate([
            'disposition' => ['required', Rule::in(['in_stock', 'service', 'writeoff'])],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        abort_unless((int) $unit->organization_id === (int) $organization->id, 404);
        abort_unless((int) $unit->warehouse()->value('branch_id') === (int) $branch->id, 404);

        $before = $unit->toArray();
        DB::transaction(function () use ($unit, $organization, $request, $validated): void {
            $locked = InventoryUnit::query()
                ->where('organization_id', $organization->id)
                ->whereKey($unit->id)
                ->lockForUpdate()
                ->firstOrFail();

            abort_unless($locked->status === 'returned', 409, __('returns.already_inspected'));

            if ($validated['disposition'] === 'in_stock') {
                $stock = Stock::query()
                    ->where('organization_id', $organization->id)
                    ->where('warehouse_id', $locked->warehouse_id)
                    ->where('product_id', $locked->product_id)
                    ->where('product_variant_id', $locked->product_variant_id)
                    ->lockForUpdate()
                    ->first();

                $stock ??= Stock::query()->create([
                    'organization_id' => $organization->id,
                    'warehouse_id' => $locked->warehouse_id,
                    'product_id' => $locked->product_id,
                    'product_variant_id' => $locked->product_variant_id,
                    'quantity' => 0,
                    'reserved_quantity' => 0,
                ]);
                $stock->update(['quantity' => (float) $stock->quantity + 1]);
            }

            $metadata = $locked->metadata ?? [];
            $metadata['return_inspection'] = [
                'disposition' => $validated['disposition'],
                'notes' => $validated['notes'] ?? null,
                'inspected_by' => $request->user()->id,
                'inspected_at' => now()->toIso8601String(),
            ];
            $locked->update([
                'status' => $validated['disposition'],
                'metadata' => $metadata,
            ]);

            InventoryMovement::query()->create([
                'organization_id' => $organization->id,
                'warehouse_id' => $locked->warehouse_id,
                'product_id' => $locked->product_id,
                'product_variant_id' => $locked->product_variant_id,
                'inventory_unit_id' => $locked->id,
                'user_id' => $request->user()->id,
                'type' => 'return_'.$validated['disposition'],
                'quantity' => $validated['disposition'] === 'in_stock' ? 1 : 0,
                'unit_cost' => $locked->purchase_price,
                'reference_type' => InventoryUnit::class,
                'reference_id' => $locked->id,
                'notes' => $validated['notes'] ?? null,
                'occurred_at' => now(),
            ]);
        }, 3);

        $audit->record('inventory.return_inspected', $unit, $before, $unit->fresh()->toArray());

        return back()->with('success', __('returns.updated'));
    }
}
