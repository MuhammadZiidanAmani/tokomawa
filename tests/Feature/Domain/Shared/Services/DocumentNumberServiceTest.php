<?php

namespace Tests\Feature\Domain\Shared\Services;

use App\Domain\Organization\Models\Branch;
use App\Domain\Shared\Services\DocumentNumberService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class DocumentNumberServiceTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_numbers_are_sequential_and_unique_within_branch_type_and_local_period(): void
    {
        $branch = Branch::factory()->create(['timezone' => 'Asia/Jakarta']);
        $service = app(DocumentNumberService::class);
        $effectiveAt = CarbonImmutable::parse('2026-10-31 18:00:00', 'UTC');

        $numbers = [];
        foreach (range(1, 50) as $unused) {
            $numbers[] = $service->next($branch, 'sale', 'POS', $effectiveAt);
        }

        $this->assertCount(50, array_unique($numbers));
        $this->assertSame('POS/2026/11/000001', $numbers[0]);
        $this->assertSame('POS/2026/11/000050', $numbers[49]);
        $this->assertDatabaseHas('document_sequences', [
            'branch_id' => $branch->id,
            'document_type' => 'sale',
            'year' => 2026,
            'month' => 11,
            'last_number' => 50,
        ]);
    }

    public function test_sequence_is_independent_per_branch_and_document_type(): void
    {
        $firstBranch = Branch::factory()->create();
        $secondBranch = Branch::factory()->create();
        $service = app(DocumentNumberService::class);
        $effectiveAt = CarbonImmutable::parse('2026-10-22 10:00:00', 'Asia/Jakarta');

        $this->assertSame('POS/2026/10/000001', $service->next($firstBranch, 'sale', 'POS', $effectiveAt));
        $this->assertSame('PUR/2026/10/000001', $service->next($firstBranch, 'purchase', 'PUR', $effectiveAt));
        $this->assertSame('POS/2026/10/000001', $service->next($secondBranch, 'sale', 'POS', $effectiveAt));
    }

    public function test_invalid_document_type_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        app(DocumentNumberService::class)->next(
            Branch::factory()->create(),
            'Sale Invalid',
            'POS',
            CarbonImmutable::now(),
        );
    }

    public function test_invalid_prefix_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        app(DocumentNumberService::class)->next(
            Branch::factory()->create(),
            'sale',
            'pos!',
            CarbonImmutable::now(),
        );
    }
}
