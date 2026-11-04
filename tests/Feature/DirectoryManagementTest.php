<?php

namespace Tests\Feature;

use App\Http\Controllers\DirectoryController;
use App\Models\Brand;
use App\Models\Customer;
use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\Feature\Concerns\CreatesWorkspace;
use Tests\TestCase;

class DirectoryManagementTest extends TestCase
{
    use CreatesWorkspace;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        if (! Route::has('directory.index')) {
            Route::middleware(['web', 'auth', 'organization', 'organization.role:owner,admin,branch_manager,warehouse'])
                ->prefix('app/directory-test')
                ->group(function (): void {
                    Route::get('/', [DirectoryController::class, 'index'])->name('directory.index');
                    Route::post('/brands', [DirectoryController::class, 'storeBrand'])->name('directory.brands.store');
                    Route::patch('/brands/{brand}', [DirectoryController::class, 'updateBrand'])->name('directory.brands.update');
                    Route::delete('/brands/{brand}', [DirectoryController::class, 'archiveBrand'])->name('directory.brands.archive');
                    Route::post('/suppliers', [DirectoryController::class, 'storeSupplier'])->name('directory.suppliers.store');
                    Route::patch('/suppliers/{supplier}', [DirectoryController::class, 'updateSupplier'])->name('directory.suppliers.update');
                    Route::delete('/suppliers/{supplier}', [DirectoryController::class, 'archiveSupplier'])->name('directory.suppliers.archive');
                    Route::post('/customers', [DirectoryController::class, 'storeCustomer'])->name('directory.customers.store');
                    Route::patch('/customers/{customer}', [DirectoryController::class, 'updateCustomer'])->name('directory.customers.update');
                    Route::delete('/customers/{customer}', [DirectoryController::class, 'archiveCustomer'])->name('directory.customers.archive');
                });

            Route::getRoutes()->refreshNameLookups();
        }
    }

    public function test_owner_creates_working_brand_supplier_and_customer_records_with_audit(): void
    {
        $workspace = $this->createWorkspace();
        $client = $this->actingAs($workspace['user'])->withSession($this->workspaceSession($workspace));

        $client->post(route('directory.brands.store'), ['name' => 'Apple'])->assertRedirect();
        $client->post(route('directory.suppliers.store'), [
            'name' => 'Caspian Distribution',
            'tax_id' => 'AZ-1002003',
            'contact_name' => 'A. Karimov',
            'phone' => '+994501112233',
            'email' => 'sales@example.test',
        ])->assertRedirect();
        $client->post(route('directory.customers.store'), [
            'name' => 'Nigar Aliyeva',
            'phone' => '+994551234567',
            'email' => 'nigar@example.test',
        ])->assertRedirect();

        $this->assertDatabaseHas('brands', ['organization_id' => $workspace['organization']->id, 'slug' => 'apple', 'is_active' => true]);
        $this->assertDatabaseHas('suppliers', ['organization_id' => $workspace['organization']->id, 'tax_id' => 'AZ-1002003']);
        $this->assertDatabaseHas('customers', ['organization_id' => $workspace['organization']->id, 'email' => 'nigar@example.test']);
        $this->assertDatabaseCount('audit_logs', 3);
    }

    public function test_brand_names_are_unique_inside_one_organization_and_archiving_is_reversible_data_retention(): void
    {
        $workspace = $this->createWorkspace();
        $client = $this->actingAs($workspace['user'])->withSession($this->workspaceSession($workspace));

        $client->post(route('directory.brands.store'), ['name' => 'Samsung'])->assertRedirect();
        $client->post(route('directory.brands.store'), ['name' => 'Samsung'])
            ->assertSessionHasErrors('name');

        $brand = Brand::query()->where('organization_id', $workspace['organization']->id)->firstOrFail();
        $client->patch(route('directory.brands.update', $brand), [
            'name' => 'Samsung Mobile',
            'is_active' => 1,
        ])->assertRedirect();
        $this->assertSame('samsung-mobile', $brand->fresh()->slug);

        $client->delete(route('directory.brands.archive', $brand))->assertRedirect();

        $this->assertDatabaseHas('brands', ['id' => $brand->id, 'is_active' => false]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'directory.brand.archived', 'auditable_id' => $brand->id]);
    }

    public function test_directory_records_from_another_tenant_cannot_be_changed(): void
    {
        $first = $this->createWorkspace();
        $second = $this->createWorkspace();
        $foreignBrand = Brand::query()->create([
            'organization_id' => $second['organization']->id,
            'name' => 'Foreign brand',
            'slug' => 'foreign-brand',
            'is_active' => true,
        ]);
        $foreignSupplier = Supplier::query()->create([
            'organization_id' => $second['organization']->id,
            'name' => 'Foreign supplier',
            'is_active' => true,
        ]);
        $foreignCustomer = Customer::query()->create([
            'organization_id' => $second['organization']->id,
            'name' => 'Foreign customer',
            'is_active' => true,
        ]);

        $client = $this->actingAs($first['user'])->withSession($this->workspaceSession($first));
        $client->patch(route('directory.brands.update', $foreignBrand), ['name' => 'Stolen', 'is_active' => 1])->assertNotFound();
        $client->delete(route('directory.suppliers.archive', $foreignSupplier))->assertNotFound();
        $client->delete(route('directory.customers.archive', $foreignCustomer))->assertNotFound();

        $this->assertSame('Foreign brand', $foreignBrand->fresh()->name);
        $this->assertTrue($foreignSupplier->fresh()->is_active);
        $this->assertTrue($foreignCustomer->fresh()->is_active);
    }

    public function test_warehouse_role_can_manage_suppliers_but_not_brands_or_customers(): void
    {
        $workspace = $this->createWorkspace(role: 'warehouse');
        $client = $this->actingAs($workspace['user'])->withSession($this->workspaceSession($workspace));

        $client->get(route('directory.index', ['tab' => 'suppliers']))
            ->assertOk()
            ->assertSee(route('directory.suppliers.store'), false)
            ->assertDontSee(route('directory.brands.store'), false)
            ->assertDontSee(route('directory.customers.store'), false);

        $client->post(route('directory.suppliers.store'), ['name' => 'Warehouse supplier'])
            ->assertRedirect();
        $client->post(route('directory.brands.store'), ['name' => 'Forbidden brand'])
            ->assertForbidden();
        $client->post(route('directory.customers.store'), ['name' => 'Forbidden customer'])
            ->assertForbidden();
    }
}
