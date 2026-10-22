<?php

namespace Tests\Feature\Http\Controllers\Master;

use App\Domain\Access\Models\AuditLog;
use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Department;
use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\Unit;
use App\Domain\Organization\Models\Branch;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class ReferenceDataControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_manager_creates_hierarchical_references_and_unit_with_audit_trail(): void
    {
        $this->seed();
        $manager = $this->manager();

        $this->actingAs($manager)->post(route('master.departments.store'), [
            'code' => 'makanan', 'name' => 'Makanan', 'description' => 'Produk makanan.',
            'is_active' => '1', 'reason' => 'Menambah departemen.',
        ])->assertSessionHasNoErrors()->assertSessionHas('status');

        $department = Department::query()->where('code', 'MAKANAN')->firstOrFail();
        $this->actingAs($manager)->post(route('master.categories.store'), [
            'department_id' => $department->id, 'code' => 'SEMBAKO', 'name' => 'Sembako',
            'is_active' => '1', 'reason' => 'Menambah kategori.',
        ])->assertSessionHasNoErrors();

        $category = Category::query()->where('code', 'SEMBAKO')->firstOrFail();
        $this->actingAs($manager)->post(route('master.subcategories.store'), [
            'category_id' => $category->id, 'code' => 'BERAS', 'name' => 'Beras',
            'is_active' => '1', 'reason' => 'Menambah subkategori.',
        ])->assertSessionHasNoErrors();

        $this->actingAs($manager)->post(route('master.brands.store'), [
            'code' => 'MAWA', 'name' => 'Mawa', 'is_active' => '1', 'reason' => 'Menambah merek.',
        ])->assertSessionHasNoErrors();
        $this->actingAs($manager)->post(route('master.units.store'), [
            'code' => 'KG', 'name' => 'Kilogram', 'symbol' => 'kg', 'allow_fractional' => '1',
            'decimal_places' => 3, 'is_active' => '1', 'reason' => 'Menambah satuan presisi.',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('subcategories', ['category_id' => $category->id, 'code' => 'BERAS']);
        $this->assertDatabaseHas('brands', ['code' => 'MAWA']);
        $this->assertDatabaseHas('units', ['code' => 'KG', 'symbol' => 'KG', 'allow_fractional' => true, 'decimal_places' => 3]);
        $this->assertSame(5, AuditLog::query()->whereIn('action', [
            'department.created', 'category.created', 'subcategory.created', 'brand.created', 'unit.created',
        ])->count());

        $unit = Unit::query()->where('code', 'KG')->firstOrFail();
        $this->actingAs($manager)->put(route('master.units.update', $unit), [
            'code' => 'KG', 'name' => 'Kilogram Diperbarui', 'symbol' => 'KG',
            'allow_fractional' => '1', 'decimal_places' => 3, 'is_active' => '0',
            'reason' => 'Menonaktifkan satuan.',
        ])->assertSessionHasNoErrors();
        $this->assertFalse($unit->fresh()->is_active);
        $this->assertDatabaseHas('audit_logs', ['action' => 'unit.updated', 'entity_id' => $unit->public_id]);
    }

    public function test_duplicate_codes_and_inactive_parent_are_rejected(): void
    {
        $this->seed();
        $manager = $this->manager();
        Department::factory()->create(['code' => 'FOOD']);
        $inactiveDepartment = Department::factory()->create(['is_active' => false]);

        $this->actingAs($manager)->post(route('master.departments.store'), [
            'code' => 'food', 'name' => 'Duplikat', 'is_active' => '1', 'reason' => 'Uji duplikasi.',
        ])->assertSessionHasErrors(['code' => 'Kode departemen sudah digunakan.']);
        $this->actingAs($manager)->post(route('master.categories.store'), [
            'department_id' => $inactiveDepartment->id, 'code' => 'CAT', 'name' => 'Kategori',
            'is_active' => '1', 'reason' => 'Uji parent.',
        ])->assertSessionHasErrors(['department_id' => 'Departemen aktif tidak ditemukan.']);

        $this->assertDatabaseCount('categories', 0);
    }

    public function test_reference_foreign_keys_prevent_destructive_parent_deletion(): void
    {
        $unit = Unit::factory()->create();
        Product::factory()->for($unit, 'baseUnit')->create();

        $this->expectException(QueryException::class);
        $unit->delete();
    }

    public function test_cashier_can_view_but_cannot_mutate_reference_data(): void
    {
        $this->seed();
        $branch = Branch::factory()->create();
        $cashier = User::factory()->withRole('cashier')->forBranch($branch)->create();

        $this->actingAs($cashier)->get(route('master.references.index'))->assertOk();
        $this->actingAs($cashier)->post(route('master.departments.store'), [
            'code' => 'NOPE', 'name' => 'Tidak boleh', 'is_active' => '1', 'reason' => 'Uji RBAC.',
        ])->assertForbidden();
    }

    private function manager(): User
    {
        $branch = Branch::factory()->create();

        return User::factory()->withRole('store_manager')->forBranch($branch)->create();
    }
}
