<?php

namespace Tests\Feature;

use App\Domain\Organization\Models\Branch;
use App\Domain\Organization\Models\Register;
use App\Domain\Organization\Models\Warehouse;
use App\Domain\Sales\Models\CashMovement;
use App\Domain\Sales\Services\CashShiftService;
use App\Models\User;
use DomainException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CashShiftServiceTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_cashier_opens_shift_exactly_once_with_opening_cash_ledger(): void
    {
        [$branch, $register, $warehouse, $cashier] = $this->context();
        $key = (string) Str::uuid();
        $service = app(CashShiftService::class);

        $first = $service->open($cashier, $branch, $register->id, $warehouse->id, '125000.50', $key);
        $replay = $service->open($cashier, $branch, $register->id, $warehouse->id, '125000.50', $key);

        $this->assertTrue($first->is($replay));
        $this->assertSame('125000.50', $first->opening_float);
        $this->assertDatabaseCount('cash_shifts', 1);
        $this->assertDatabaseCount('cash_movements', 1);
        $this->assertSame('125000.50', CashMovement::query()->sole()->amount_delta);
        $this->assertDatabaseHas('audit_logs', ['action' => 'cash_shift.opened', 'entity_id' => $first->public_id]);
        $this->assertDatabaseHas('outbox_events', ['event_type' => 'cash_shift.opened', 'aggregate_id' => $first->public_id]);
    }

    public function test_register_cannot_have_two_active_shifts(): void
    {
        [$branch, $register, $warehouse, $cashier] = $this->context();
        $service = app(CashShiftService::class);
        $service->open($cashier, $branch, $register->id, $warehouse->id, '0', (string) Str::uuid());

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Register sedang digunakan');
        $service->open(
            User::factory()->withRole('cashier')->forBranch($branch)->create(),
            $branch,
            $register->id,
            $warehouse->id,
            '0',
            (string) Str::uuid(),
        );
    }

    /** @return array{Branch, Register, Warehouse, User} */
    private function context(): array
    {
        $this->seed();
        $branch = Branch::factory()->create();
        $register = Register::factory()->for($branch)->create(['is_active' => true]);
        $warehouse = Warehouse::factory()->for($branch)->create(['is_active' => true]);
        $cashier = User::factory()->withRole('cashier')->forBranch($branch)->create(['is_active' => true]);

        return [$branch, $register, $warehouse, $cashier];
    }
}
