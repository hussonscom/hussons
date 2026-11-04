<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\CreatesWorkspace;
use Tests\TestCase;

class LandingAndAuthenticationTest extends TestCase
{
    use CreatesWorkspace;
    use RefreshDatabase;

    public function test_public_landing_page_exposes_the_product_and_workspaces(): void
    {
        $this->withoutVite();

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Hussons One')
            ->assertSee('Elektronika')
            ->assertSee('Aptek');
    }

    public function test_registration_creates_an_isolated_owner_workspace(): void
    {
        $password = 'StrongPass9081';

        $response = $this->post(route('register.store'), [
            'name' => 'Workspace Owner',
            'email' => 'owner@example.test',
            'password' => $password,
            'password_confirmation' => $password,
            'organization_name' => 'One Retail',
            'business_types' => ['electronics', 'pharmacy', 'restaurant'],
            'locale' => 'az',
            'terms' => '1',
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticated();

        $user = User::query()->where('email', 'owner@example.test')->firstOrFail();
        $organization = Organization::query()->where('name', 'One Retail')->firstOrFail();

        $this->assertDatabaseHas('memberships', [
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'role' => 'owner',
            'is_active' => true,
        ]);
        $this->assertDatabaseHas('branches', ['organization_id' => $organization->id, 'code' => 'MAIN']);
        $this->assertDatabaseHas('warehouses', ['organization_id' => $organization->id, 'code' => 'MAIN']);
    }

    public function test_active_member_can_log_in_and_logout_with_workspace_context(): void
    {
        $password = 'LoginPass7412';
        $user = User::factory()->create([
            'email' => 'member@example.test',
            'password' => $password,
            'is_active' => true,
        ]);
        $workspace = $this->createWorkspace($user, 'cashier');

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => $password,
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
        $this->assertSame($workspace['organization']->id, session('organization_id'));

        $this->post(route('logout'))->assertRedirect(route('home'));
        $this->assertGuest();
    }
}
