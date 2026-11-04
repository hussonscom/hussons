<?php

namespace Tests\Feature;

use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\Feature\Concerns\CreatesWorkspace;
use Tests\TestCase;

class ModuleAccessTest extends TestCase
{
    use CreatesWorkspace;
    use RefreshDatabase;

    public function test_disabled_industry_module_is_hidden_and_blocked(): void
    {
        $workspace = $this->createWorkspace();
        $workspace['organization']->update(['modules' => ['retail']]);

        Route::middleware(['web', 'auth', 'organization', 'organization.module:pharmacy'])
            ->get('/_test/pharmacy-module', fn () => 'enabled');

        $client = $this->actingAs($workspace['user'])
            ->withSession($this->workspaceSession($workspace));

        $client->get('/_test/pharmacy-module')->assertNotFound();
        $client->get(route('dashboard'))
            ->assertDontSee('data-nav-route="pharmacy.index"', false)
            ->assertDontSee('data-nav-route="restaurant.index"', false)
            ->assertSee('data-nav-route="catalog.index"', false);
    }

    public function test_enabled_module_and_cafe_alias_are_allowed(): void
    {
        $workspace = $this->createWorkspace();
        $workspace['organization']->update(['modules' => ['pharmacy', 'cafe']]);

        Route::middleware(['web', 'auth', 'organization', 'organization.module:pharmacy'])
            ->get('/_test/pharmacy-enabled', fn () => 'enabled');
        Route::middleware(['web', 'auth', 'organization', 'organization.module:restaurant'])
            ->get('/_test/restaurant-enabled', fn () => 'enabled');

        $client = $this->actingAs($workspace['user'])
            ->withSession($this->workspaceSession($workspace));

        $client->get('/_test/pharmacy-enabled')->assertOk()->assertSee('enabled');
        $client->get('/_test/restaurant-enabled')->assertOk()->assertSee('enabled');
        $client->get(route('dashboard'))
            ->assertSee('data-nav-route="pharmacy.index"', false)
            ->assertSee('data-nav-route="restaurant.index"', false);

        $workspace['organization']->update(['modules' => ['food']]);
        $client->get('/_test/restaurant-enabled')->assertOk()->assertSee('enabled');
        $client->get(route('dashboard'))
            ->assertSee('data-nav-route="restaurant.index"', false)
            ->assertDontSee('data-nav-route="pharmacy.index"', false);
    }

    public function test_navigation_respects_membership_role(): void
    {
        $workspace = $this->createWorkspace(role: 'cashier');

        $this->actingAs($workspace['user'])
            ->withSession($this->workspaceSession($workspace))
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('data-nav-route="sales.index"', false)
            ->assertSee('data-nav-route="restaurant.index"', false)
            ->assertSee('data-nav-route="catalog.index"', false)
            ->assertDontSee('data-nav-route="inventory.index"', false)
            ->assertDontSee('data-nav-route="team.index"', false);
    }

    public function test_read_only_catalog_roles_get_navigation_without_mutation_actions(): void
    {
        $workspace = $this->createWorkspace(role: 'cashier');
        $product = $this->createProduct($workspace['organization']);

        $this->actingAs($workspace['user'])
            ->withSession($this->workspaceSession($workspace))
            ->get(route('catalog.index'))
            ->assertOk()
            ->assertSee('data-nav-route="catalog.index"', false)
            ->assertSee($product->name_az)
            ->assertDontSee(route('catalog.create'), false)
            ->assertDontSee(route('catalog.edit', $product), false)
            ->assertDontSee(route('catalog.destroy', $product), false);
    }

    public function test_catalog_categories_follow_enabled_modules_and_empty_legacy_configuration_sees_all(): void
    {
        $workspace = $this->createWorkspace();
        $categories = collect([
            'electronics' => 'Electronics category',
            'retail' => 'Retail category',
            'pharmacy' => 'Pharmacy category',
            'food' => 'Food category',
        ])->mapWithKeys(function (string $name, string $businessType): array {
            $category = Category::query()->create([
                'organization_id' => null,
                'business_type' => $businessType,
                'slug' => 'test-'.$businessType,
                'name_az' => $name,
                'name_ru' => $name,
                'name_en' => $name,
                'sort_order' => 1,
                'is_active' => true,
            ]);

            return [$businessType => $category];
        });

        $client = $this->actingAs($workspace['user'])
            ->withSession($this->workspaceSession($workspace));

        $workspace['organization']->update(['modules' => ['electronics', 'cafe']]);
        $client->get(route('catalog.create'))
            ->assertOk()
            ->assertSee($categories['electronics']->name_az)
            ->assertSee($categories['food']->name_az)
            ->assertDontSee($categories['retail']->name_az)
            ->assertDontSee($categories['pharmacy']->name_az);

        $workspace['organization']->update(['modules' => ['retail', 'pharmacy']]);
        $client->get(route('catalog.create'))
            ->assertOk()
            ->assertSee($categories['retail']->name_az)
            ->assertSee($categories['pharmacy']->name_az)
            ->assertDontSee($categories['electronics']->name_az)
            ->assertDontSee($categories['food']->name_az);

        $workspace['organization']->update(['modules' => []]);
        $legacyResponse = $client->get(route('catalog.create'))->assertOk();
        foreach ($categories as $category) {
            $legacyResponse->assertSee($category->name_az);
        }
    }
}
