<?php

namespace Tests\Feature\Domain\Shared\Services;

use App\Domain\Organization\Models\Branch;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class DocumentNumberConcurrencyTest extends TestCase
{
    public function test_two_independent_processes_allocate_unique_sequential_numbers_on_mariadb(): void
    {
        $this->assertSame('mariadb', config('database.default'));
        $this->assertSame('retail_testing', config('database.connections.mariadb.database'));

        Artisan::call('migrate:fresh', ['--force' => true]);

        $branch = Branch::factory()->create(['timezone' => 'Asia/Jakarta']);
        $barrierPath = storage_path('framework/testing/'.Str::ulid().'.start');
        File::ensureDirectoryExists(dirname($barrierPath));

        $processes = [
            $this->documentNumberWorker($branch, $barrierPath, 25),
            $this->documentNumberWorker($branch, $barrierPath, 25),
        ];

        try {
            foreach ($processes as $process) {
                $process->start();
            }

            File::put($barrierPath, 'start');

            $numbers = [];
            foreach ($processes as $process) {
                $process->wait();
                $this->assertTrue(
                    $process->isSuccessful(),
                    trim($process->getErrorOutput().' '.$process->getOutput()),
                );

                $output = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
                $this->assertIsArray($output);
                $numbers = [...$numbers, ...$output];
            }

            sort($numbers);

            $this->assertCount(50, $numbers);
            $this->assertCount(50, array_unique($numbers));
            $this->assertSame('POS/2026/10/000001', $numbers[0]);
            $this->assertSame('POS/2026/10/000050', $numbers[49]);
            $this->assertDatabaseHas('document_sequences', [
                'branch_id' => $branch->getKey(),
                'document_type' => 'sale',
                'year' => 2026,
                'month' => 10,
                'last_number' => 50,
            ]);
        } finally {
            File::delete($barrierPath);
            Artisan::call('migrate:fresh', ['--force' => true]);
        }
    }

    private function documentNumberWorker(Branch $branch, string $barrierPath, int $allocations): Process
    {
        return new Process(
            [
                PHP_BINARY,
                '-r',
                $this->workerCode(),
                '--',
                (string) $branch->getKey(),
                $barrierPath,
                (string) $allocations,
            ],
            base_path(),
            $this->workerEnvironment(),
            timeout: 30,
        );
    }

    /** @return array<string, string> */
    private function workerEnvironment(): array
    {
        /** @var array<string, mixed> $database */
        $database = config('database.connections.mariadb');

        return [
            'APP_ENV' => 'testing',
            'APP_DEBUG' => 'false',
            'APP_KEY' => (string) config('app.key'),
            'DB_CONNECTION' => 'mariadb',
            'DB_URL' => '',
            'DB_HOST' => (string) $database['host'],
            'DB_PORT' => (string) $database['port'],
            'DB_DATABASE' => (string) $database['database'],
            'DB_USERNAME' => (string) $database['username'],
            'DB_PASSWORD' => (string) $database['password'],
            'DB_SOCKET' => (string) $database['unix_socket'],
            'DB_CHARSET' => (string) $database['charset'],
            'DB_COLLATION' => (string) $database['collation'],
        ];
    }

    private function workerCode(): string
    {
        return <<<'PHP'
require getcwd().'/vendor/autoload.php';
$app = require getcwd().'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

while (! is_file($argv[2])) {
    usleep(1_000);
}

$branch = \App\Domain\Organization\Models\Branch::query()->findOrFail((int) $argv[1]);
$service = $app->make(\App\Domain\Shared\Services\DocumentNumberService::class);
$effectiveAt = \Carbon\CarbonImmutable::parse('2026-10-22 10:00:00', 'Asia/Jakarta');
$numbers = [];

for ($index = 0; $index < (int) $argv[3]; $index++) {
    $numbers[] = $service->next($branch, 'sale', 'POS', $effectiveAt);
}

echo json_encode($numbers, JSON_THROW_ON_ERROR);
PHP;
    }
}
