<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveProductRequest;
use App\Models\Brand;
use App\Models\Category;
use App\Models\InventoryLot;
use App\Models\InventoryUnit;
use App\Models\Organization;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Stock;
use App\Services\AuditLogger;
use App\Services\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CatalogController extends Controller
{
    public function index(Request $request, TenantContext $context)
    {
        $organization = $context->organization($request);
        $products = Product::query()
            ->with(['category', 'brand', 'variants'])
            ->withSum('stocks', 'quantity')
            ->where('organization_id', $organization->id)
            ->when($request->filled('q'), function ($query) use ($request) {
                $term = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $request->string('q')->trim()).'%';
                $query->where(fn ($inner) => $inner
                    ->where('sku', 'like', $term)
                    ->orWhere('barcode', 'like', $term)
                    ->orWhere('name_az', 'like', $term)
                    ->orWhere('name_ru', 'like', $term)
                    ->orWhere('name_en', 'like', $term));
            })
            ->when($request->filled('type'), fn ($query) => $query->where('type', $request->string('type')))
            ->when($request->boolean('inactive'), fn ($query) => $query->where('is_active', false), fn ($query) => $query->where('is_active', true))
            ->latest('id')
            ->paginate(24)
            ->withQueryString();

        return view('catalog.index', compact('products'));
    }

    public function create(Request $request, TenantContext $context)
    {
        return view('catalog.form', $this->formData($context->organization($request)));
    }

    public function store(SaveProductRequest $request, TenantContext $context, AuditLogger $audit)
    {
        $organization = $context->organization($request);
        $product = DB::transaction(function () use ($request, $organization) {
            $data = $request->validated();
            $this->ensureUniqueIdentifiers($organization->id, $data);
            $this->ensureRelatedRecordsBelongToOrganization($organization->id, $data);

            $product = Product::query()->create($this->productPayload($organization->id, $data));
            $this->syncVariants($product, $data);

            return $product;
        });

        $audit->record('product.created', $product, null, $product->fresh()->toArray());

        return redirect()->route('catalog.edit', $product)->with('status', __('Product created.'));
    }

    public function edit(Request $request, Product $product, TenantContext $context)
    {
        $organization = $context->organization($request);
        abort_unless((int) $product->organization_id === (int) $organization->id, 404);

        return view('catalog.form', array_merge($this->formData($organization), [
            'product' => $product->load('variants'),
        ]));
    }

    public function update(SaveProductRequest $request, Product $product, TenantContext $context, AuditLogger $audit)
    {
        $organization = $context->organization($request);
        abort_unless((int) $product->organization_id === (int) $organization->id, 404);

        $before = $product->toArray();
        DB::transaction(function () use ($request, $organization, $product) {
            $data = $request->validated();
            $this->ensureUniqueIdentifiers($organization->id, $data, $product->id);
            $this->ensureRelatedRecordsBelongToOrganization($organization->id, $data);
            $this->ensureInventoryTrackingCanChange($product, $data);
            $product->update($this->productPayload($organization->id, $data));
            $this->syncVariants($product, $data);
        });
        $audit->record('product.updated', $product, $before, $product->fresh()->toArray());

        return back()->with('status', __('Product updated.'));
    }

    public function destroy(Request $request, Product $product, TenantContext $context, AuditLogger $audit)
    {
        $organization = $context->organization($request);
        abort_unless((int) $product->organization_id === (int) $organization->id, 404);
        $before = $product->toArray();
        $product->update(['is_active' => false]);
        $audit->record('product.archived', $product, $before, $product->fresh()->toArray());

        return redirect()->route('catalog.index')->with('status', __('Product archived.'));
    }

    private function formData(Organization $organization): array
    {
        $modules = collect($organization->modules ?? [])
            ->filter(fn (mixed $module): bool => is_string($module) && trim($module) !== '')
            ->map(fn (string $module): string => strtolower(trim($module)))
            ->unique()
            ->values();

        $businessTypes = $modules
            ->flatMap(fn (string $module): array => match ($module) {
                'electronics' => ['electronics'],
                'retail' => ['retail'],
                'pharmacy' => ['pharmacy'],
                'restaurant', 'cafe', 'food' => ['food'],
                default => [],
            })
            ->unique()
            ->values();

        return [
            'product' => null,
            'categories' => Category::query()
                ->where(fn ($query) => $query->whereNull('organization_id')->orWhere('organization_id', $organization->id))
                ->when($modules->isNotEmpty(), fn ($query) => $query->whereIn('business_type', $businessTypes))
                ->where('is_active', true)
                ->orderBy('business_type')
                ->orderBy('sort_order')
                ->get(),
            'brands' => Brand::query()
                ->where('organization_id', $organization->id)
                ->where('is_active', true)
                ->orderBy('name')
                ->get(),
        ];
    }

    private function productPayload(int $organizationId, array $data): array
    {
        $metadata = [
            'model' => $data['model'] ?? null,
            'color' => $data['color'] ?? null,
            'memory_capacity' => $data['memory_capacity'] ?? null,
            'warranty_months' => $data['warranty_months'] ?? null,
            'active_ingredient' => $data['active_ingredient'] ?? null,
            'dosage' => $data['dosage'] ?? null,
            'dosage_form' => $data['dosage_form'] ?? null,
            'manufacturer' => $data['manufacturer'] ?? null,
            'prescription_required' => (bool) ($data['prescription_required'] ?? false),
        ];

        return [
            'organization_id' => $organizationId,
            'category_id' => $data['category_id'] ?? null,
            'brand_id' => $data['brand_id'] ?? null,
            'type' => $data['type'],
            'sku' => $data['sku'],
            'barcode' => $data['barcode'] ?? null,
            'name_az' => $data['name_az'],
            'name_ru' => $data['name_ru'] ?? null,
            'name_en' => $data['name_en'] ?? null,
            'description_az' => $data['description'] ?? null,
            'unit' => $data['unit'] === 'piece' ? 'pcs' : $data['unit'],
            'tracking_mode' => $data['tracking_mode'],
            'min_stock' => $data['min_stock'] ?? 0,
            'purchase_price' => $data['purchase_price'],
            'sale_price' => $data['sale_price'],
            'tax_rate' => $data['tax_rate'] ?? 0,
            'is_active' => (bool) ($data['active'] ?? true),
            'metadata' => array_filter($metadata, fn ($value) => $value !== null && $value !== ''),
        ];
    }

    private function syncVariants(Product $product, array $data): void
    {
        if (array_key_exists('variants', $data)) {
            $rows = array_values($data['variants'] ?? []);
            $seenSkus = [];
            $seenBarcodes = [];

            foreach ($rows as $index => $row) {
                $variant = null;
                if (! empty($row['id'])) {
                    $variant = ProductVariant::query()
                        ->where('organization_id', $product->organization_id)
                        ->where('product_id', $product->id)
                        ->findOrFail($row['id']);
                }

                if ((bool) ($row['remove'] ?? false)) {
                    if (! $variant) {
                        continue;
                    }

                    if ($this->variantHasAvailableInventory($variant)) {
                        throw ValidationException::withMessages([
                            "variants.$index.remove" => __('A variant with stock cannot be removed.'),
                        ]);
                    }

                    $variant->update(['is_active' => false]);

                    continue;
                }

                if ($variant && $variant->is_active && ! (bool) ($row['active'] ?? true)
                    && $this->variantHasAvailableInventory($variant)) {
                    throw ValidationException::withMessages([
                        "variants.$index.active" => __('A variant with stock cannot be deactivated.'),
                    ]);
                }

                $sku = trim((string) $row['sku']);
                $normalizedSku = mb_strtolower($sku);
                if (isset($seenSkus[$normalizedSku])) {
                    throw ValidationException::withMessages([
                        "variants.$index.sku" => __('Variant SKUs must be unique.'),
                    ]);
                }
                $seenSkus[$normalizedSku] = true;

                $barcode = filled($row['barcode'] ?? null) ? trim((string) $row['barcode']) : null;
                if ($barcode !== null) {
                    $normalizedBarcode = mb_strtolower($barcode);
                    if (isset($seenBarcodes[$normalizedBarcode])) {
                        throw ValidationException::withMessages([
                            "variants.$index.barcode" => __('Variant barcodes must be unique.'),
                        ]);
                    }
                    $seenBarcodes[$normalizedBarcode] = true;
                }

                $skuTaken = ProductVariant::query()
                    ->where('organization_id', $product->organization_id)
                    ->where('sku', $sku)
                    ->when($variant, fn ($query) => $query->where('id', '!=', $variant->id))
                    ->exists()
                    || Product::query()
                        ->where('organization_id', $product->organization_id)
                        ->where('id', '!=', $product->id)
                        ->where('sku', $sku)
                        ->exists();

                $barcodeTaken = $barcode !== null && (
                    ProductVariant::query()
                        ->where('organization_id', $product->organization_id)
                        ->where('barcode', $barcode)
                        ->when($variant, fn ($query) => $query->where('id', '!=', $variant->id))
                        ->exists()
                    || Product::query()
                        ->where('organization_id', $product->organization_id)
                        ->where('id', '!=', $product->id)
                        ->where('barcode', $barcode)
                        ->exists()
                );

                if ($skuTaken || $barcodeTaken) {
                    throw ValidationException::withMessages([
                        $skuTaken ? "variants.$index.sku" : "variants.$index.barcode" => __('This identifier is already used in the organization.'),
                    ]);
                }

                $payload = [
                    'organization_id' => $product->organization_id,
                    'product_id' => $product->id,
                    'sku' => $sku,
                    'barcode' => $barcode,
                    'model' => $row['model'] ?? null,
                    'color' => $row['color'] ?? null,
                    'memory_capacity' => $row['memory_capacity'] ?? null,
                    'attributes' => [],
                    'purchase_price' => $row['purchase_price'] ?? $product->purchase_price,
                    'sale_price' => $row['sale_price'] ?? $product->sale_price,
                    'warranty_months' => $row['warranty_months'] ?? 0,
                    'is_active' => (bool) ($row['active'] ?? true),
                ];

                $variant ? $variant->update($payload) : ProductVariant::query()->create($payload);
            }

            return;
        }

        if (! collect(['model', 'color', 'memory_capacity'])->contains(fn ($field) => filled($data[$field] ?? null))) {
            return;
        }

        $product->variants()->updateOrCreate(
            ['sku' => $data['sku']],
            [
                'organization_id' => $product->organization_id,
                'barcode' => $data['barcode'] ?? null,
                'model' => $data['model'] ?? null,
                'color' => $data['color'] ?? null,
                'memory_capacity' => $data['memory_capacity'] ?? null,
                'attributes' => [],
                'purchase_price' => $data['purchase_price'],
                'sale_price' => $data['sale_price'],
                'warranty_months' => $data['warranty_months'] ?? 0,
                'is_active' => true,
            ],
        );
    }

    private function ensureUniqueIdentifiers(int $organizationId, array $data, ?int $ignoreId = null): void
    {
        $skuExists = Product::query()
            ->where('organization_id', $organizationId)
            ->where('sku', $data['sku'])
            ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))
            ->exists()
            || ProductVariant::query()
                ->where('organization_id', $organizationId)
                ->where('sku', $data['sku'])
                ->when($ignoreId, fn ($query) => $query->where('product_id', '!=', $ignoreId))
                ->exists();
        $barcodeExists = filled($data['barcode'] ?? null) && Product::query()
            ->where('organization_id', $organizationId)
            ->where('barcode', $data['barcode'])
            ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))
            ->exists();
        $barcodeExists = $barcodeExists || (filled($data['barcode'] ?? null) && ProductVariant::query()
            ->where('organization_id', $organizationId)
            ->where('barcode', $data['barcode'])
            ->when($ignoreId, fn ($query) => $query->where('product_id', '!=', $ignoreId))
            ->exists());

        if ($skuExists || $barcodeExists) {
            throw ValidationException::withMessages([
                $skuExists ? 'sku' : 'barcode' => __('This identifier is already used in the organization.'),
            ]);
        }
    }

    private function ensureRelatedRecordsBelongToOrganization(int $organizationId, array $data): void
    {
        if (! empty($data['category_id'])) {
            $validCategory = Category::query()
                ->whereKey($data['category_id'])
                ->where(fn ($query) => $query->whereNull('organization_id')->orWhere('organization_id', $organizationId))
                ->exists();
            abort_unless($validCategory, 404);
        }

        if (! empty($data['brand_id'])) {
            abort_unless(Brand::query()
                ->where('organization_id', $organizationId)
                ->whereKey($data['brand_id'])
                ->exists(), 404);
        }
    }

    private function ensureInventoryTrackingCanChange(Product $product, array $data): void
    {
        $trackingChanges = $data['tracking_mode'] !== $product->tracking_mode;
        $leavesDrugCatalog = $product->type === 'drug' && $data['type'] !== 'drug';

        if (! $trackingChanges && ! $leavesDrugCatalog) {
            return;
        }

        $hasInventoryHistory = Stock::query()->where('product_id', $product->id)->exists()
            || InventoryUnit::query()->where('product_id', $product->id)->exists()
            || InventoryLot::query()->where('product_id', $product->id)->exists();

        if (! $hasInventoryHistory) {
            return;
        }

        $errors = [];
        if ($trackingChanges) {
            $errors['tracking_mode'] = __('Tracking mode cannot be changed after inventory activity.');
        }
        if ($leavesDrugCatalog) {
            $errors['type'] = __('A drug with inventory activity cannot be changed to another product type.');
        }

        throw ValidationException::withMessages($errors);
    }

    private function variantHasAvailableInventory(ProductVariant $variant): bool
    {
        return Stock::query()
            ->where('organization_id', $variant->organization_id)
            ->where('product_variant_id', $variant->id)
            ->where(function ($query): void {
                $query->where('quantity', '>', 0)->orWhere('reserved_quantity', '>', 0);
            })
            ->exists()
            || InventoryUnit::query()
                ->where('organization_id', $variant->organization_id)
                ->where('product_variant_id', $variant->id)
                ->whereIn('status', ['in_stock', 'reserved', 'returned'])
                ->exists()
            || InventoryLot::query()
                ->where('organization_id', $variant->organization_id)
                ->where('product_variant_id', $variant->id)
                ->where('quantity_available', '>', 0)
                ->exists();
    }
}
