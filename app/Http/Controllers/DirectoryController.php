<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\Customer;
use App\Models\Supplier;
use App\Services\AuditLogger;
use App\Services\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

final class DirectoryController extends Controller
{
    public function index(Request $request, TenantContext $context): View
    {
        $organization = $context->organization($request);
        $tab = in_array($request->string('tab')->toString(), ['brands', 'suppliers', 'customers'], true)
            ? $request->string('tab')->toString()
            : 'brands';
        $search = trim($request->string('q')->toString());

        $brands = Brand::query()
            ->where('organization_id', $organization->id)
            ->when($search !== '', fn (Builder $query) => $query->where('name', 'like', "%{$search}%"))
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->paginate(20, ['*'], 'brands_page')
            ->withQueryString();

        $suppliers = Supplier::query()
            ->where('organization_id', $organization->id)
            ->when($search !== '', fn (Builder $query) => $query->where(function (Builder $inner) use ($search): void {
                $inner->where('name', 'like', "%{$search}%")
                    ->orWhere('tax_id', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            }))
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->paginate(20, ['*'], 'suppliers_page')
            ->withQueryString();

        $customers = Customer::query()
            ->where('organization_id', $organization->id)
            ->when($search !== '', fn (Builder $query) => $query->where(function (Builder $inner) use ($search): void {
                $inner->where('name', 'like', "%{$search}%")
                    ->orWhere('tax_id', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            }))
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->paginate(20, ['*'], 'customers_page')
            ->withQueryString();

        return view('directory.index', compact('tab', 'brands', 'suppliers', 'customers'));
    }

    public function storeBrand(Request $request, TenantContext $context, AuditLogger $audit): RedirectResponse
    {
        $organization = $context->organization($request);
        $data = $request->validate(['name' => ['required', 'string', 'max:160']]);
        $name = trim($data['name']);
        $slug = Str::slug($name) ?: Str::lower(Str::random(10));

        if (Brand::query()->where('organization_id', $organization->id)->where('slug', $slug)->exists()) {
            throw ValidationException::withMessages(['name' => __('directory.brand_exists')]);
        }

        $brand = Brand::query()->create([
            'organization_id' => $organization->id,
            'name' => $name,
            'slug' => $slug,
            'is_active' => true,
        ]);
        $audit->record('directory.brand.created', $brand, null, $brand->toArray());

        return $this->backTo('brands', __('directory.brand_created'));
    }

    public function updateBrand(Request $request, Brand $brand, TenantContext $context, AuditLogger $audit): RedirectResponse
    {
        $organization = $context->organization($request);
        $this->ensureTenant($brand->organization_id, $organization->id);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'is_active' => ['required', 'boolean'],
        ]);
        $slug = Str::slug(trim($data['name'])) ?: $brand->slug;
        if (Brand::query()->where('organization_id', $organization->id)->where('slug', $slug)->whereKeyNot($brand->id)->exists()) {
            throw ValidationException::withMessages(['name' => __('directory.brand_exists')]);
        }

        $before = $brand->toArray();
        $brand->update(['name' => trim($data['name']), 'slug' => $slug, 'is_active' => (bool) $data['is_active']]);
        $audit->record('directory.brand.updated', $brand, $before, $brand->fresh()->toArray());

        return $this->backTo('brands', __('directory.brand_updated'));
    }

    public function archiveBrand(Request $request, Brand $brand, TenantContext $context, AuditLogger $audit): RedirectResponse
    {
        $organization = $context->organization($request);
        $this->ensureTenant($brand->organization_id, $organization->id);
        $before = $brand->toArray();
        $brand->update(['is_active' => false]);
        $audit->record('directory.brand.archived', $brand, $before, $brand->fresh()->toArray());

        return $this->backTo('brands', __('directory.brand_archived'));
    }

    public function storeSupplier(Request $request, TenantContext $context, AuditLogger $audit): RedirectResponse
    {
        $organization = $context->organization($request);
        $data = $this->validateSupplier($request);
        $supplier = Supplier::query()->create(['organization_id' => $organization->id, ...$data, 'is_active' => true]);
        $audit->record('directory.supplier.created', $supplier, null, $supplier->toArray());

        return $this->backTo('suppliers', __('directory.supplier_created'));
    }

    public function updateSupplier(Request $request, Supplier $supplier, TenantContext $context, AuditLogger $audit): RedirectResponse
    {
        $organization = $context->organization($request);
        $this->ensureTenant($supplier->organization_id, $organization->id);
        $data = $this->validateSupplier($request, true);
        $before = $supplier->toArray();
        $supplier->update($data);
        $audit->record('directory.supplier.updated', $supplier, $before, $supplier->fresh()->toArray());

        return $this->backTo('suppliers', __('directory.supplier_updated'));
    }

    public function archiveSupplier(Request $request, Supplier $supplier, TenantContext $context, AuditLogger $audit): RedirectResponse
    {
        $organization = $context->organization($request);
        $this->ensureTenant($supplier->organization_id, $organization->id);
        $before = $supplier->toArray();
        $supplier->update(['is_active' => false]);
        $audit->record('directory.supplier.archived', $supplier, $before, $supplier->fresh()->toArray());

        return $this->backTo('suppliers', __('directory.supplier_archived'));
    }

    public function storeCustomer(Request $request, TenantContext $context, AuditLogger $audit): RedirectResponse
    {
        $organization = $context->organization($request);
        $data = $this->validateCustomer($request);
        $customer = Customer::query()->create(['organization_id' => $organization->id, ...$data, 'is_active' => true]);
        $audit->record('directory.customer.created', $customer, null, $customer->toArray());

        return $this->backTo('customers', __('directory.customer_created'));
    }

    public function updateCustomer(Request $request, Customer $customer, TenantContext $context, AuditLogger $audit): RedirectResponse
    {
        $organization = $context->organization($request);
        $this->ensureTenant($customer->organization_id, $organization->id);
        $data = $this->validateCustomer($request, true);
        $before = $customer->toArray();
        $customer->update($data);
        $audit->record('directory.customer.updated', $customer, $before, $customer->fresh()->toArray());

        return $this->backTo('customers', __('directory.customer_updated'));
    }

    public function archiveCustomer(Request $request, Customer $customer, TenantContext $context, AuditLogger $audit): RedirectResponse
    {
        $organization = $context->organization($request);
        $this->ensureTenant($customer->organization_id, $organization->id);
        $before = $customer->toArray();
        $customer->update(['is_active' => false]);
        $audit->record('directory.customer.archived', $customer, $before, $customer->fresh()->toArray());

        return $this->backTo('customers', __('directory.customer_archived'));
    }

    /** @return array<string, mixed> */
    private function validateSupplier(Request $request, bool $withStatus = false): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:180'],
            'tax_id' => ['nullable', 'string', 'max:64'],
            'contact_name' => ['nullable', 'string', 'max:180'],
            'phone' => ['nullable', 'string', 'max:32'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'is_active' => [$withStatus ? 'required' : 'sometimes', 'boolean'],
        ]);
    }

    /** @return array<string, mixed> */
    private function validateCustomer(Request $request, bool $withStatus = false): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:180'],
            'phone' => ['nullable', 'string', 'max:32'],
            'email' => ['nullable', 'email', 'max:255'],
            'tax_id' => ['nullable', 'string', 'max:64'],
            'birth_date' => ['nullable', 'date', 'before_or_equal:today'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'is_active' => [$withStatus ? 'required' : 'sometimes', 'boolean'],
        ]);
    }

    private function ensureTenant(int $recordOrganizationId, int $organizationId): void
    {
        abort_unless($recordOrganizationId === $organizationId, 404);
    }

    private function backTo(string $tab, string $message): RedirectResponse
    {
        return redirect()->route('directory.index', ['tab' => $tab])->with('status', $message);
    }
}
