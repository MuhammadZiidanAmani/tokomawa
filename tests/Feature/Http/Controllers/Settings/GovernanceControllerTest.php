<?php

namespace Tests\Feature\Http\Controllers\Settings;

use App\Domain\Organization\Models\Branch;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class GovernanceControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get(route('settings.governance'));

        $response->assertRedirect(route('login'));
    }

    public function test_user_without_settings_permission_is_forbidden(): void
    {
        $this->seed();
        $cashier = User::factory()->withRole('cashier')->create();

        $response = $this->actingAs($cashier)->get(route('settings.governance'));

        $response->assertForbidden();
    }

    public function test_super_admin_without_a_branch_can_open_governance_to_create_the_first_branch(): void
    {
        $this->seed();
        $superAdmin = User::factory()->withRole('super_admin')->create();

        $response = $this->actingAs($superAdmin)->get(route('settings.governance'));

        $response
            ->assertOk()
            ->assertSee('Tambah cabang')
            ->assertSee('Belum ada cabang. Super Admin dapat membuat cabang operasional pertama melalui formulir di samping.');
    }

    public function test_authorized_user_sees_only_accessible_branch_data_and_escaped_content(): void
    {
        $this->seed();
        $ownBranch = Branch::factory()->create(['name' => "Cabang <script>alert('x')</script>"]);
        Branch::factory()->create(['name' => 'Cabang Rahasia']);
        $owner = User::factory()->withRole('owner')->forBranch($ownBranch)->create();

        $response = $this->actingAs($owner)->get(route('settings.governance'));

        $response
            ->assertOk()
            ->assertSee('Organisasi &amp; Tata Kelola', escape: false)
            ->assertSee('&lt;script&gt;', escape: false)
            ->assertDontSee("<script>alert('x')</script>", escape: false)
            ->assertDontSee('Cabang Rahasia');
    }

    public function test_owner_does_not_see_users_assigned_only_to_another_branch(): void
    {
        $this->seed();
        $ownBranch = Branch::factory()->create();
        $foreignBranch = Branch::factory()->create();
        $owner = User::factory()->withRole('owner')->forBranch($ownBranch)->create();
        User::factory()->forBranch($ownBranch)->create(['name' => 'Pengguna Cabang Sendiri']);
        User::factory()->forBranch($foreignBranch)->create(['name' => 'Pengguna Cabang Rahasia']);

        $response = $this->actingAs($owner)->get(route('settings.governance'));

        $response
            ->assertOk()
            ->assertSee('Pengguna Cabang Sendiri')
            ->assertDontSee('Pengguna Cabang Rahasia');
    }

    public function test_switching_to_an_inaccessible_branch_returns_not_found(): void
    {
        $this->seed();
        $ownBranch = Branch::factory()->create();
        $foreignBranch = Branch::factory()->create();
        $manager = User::factory()->withRole('store_manager')->forBranch($ownBranch)->create();

        $response = $this->actingAs($manager)->post(route('current-branch.store'), [
            'branch' => $foreignBranch->public_id,
        ]);

        $response->assertNotFound();
        $this->assertNotSame($foreignBranch->public_id, session('current_branch'));
    }
}
