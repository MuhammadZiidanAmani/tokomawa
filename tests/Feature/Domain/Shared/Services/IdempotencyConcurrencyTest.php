<?php

namespace Tests\Feature\Domain\Shared\Services;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class IdempotencyConcurrencyTest extends TestCase
{
    public function test_two_independent_processes_execute_an_idempotent_operation_once_on_mariadb(): void
    {
        $this->assertSame('mariadb', config('database.default'));
        $this->assertSame('retail_testing', config('database.connections.mariadb.database'));

        Artisan::call('migrate:fresh', ['--force' => true]);

        $barrierPath = storage_path('framework/testing/'.Str::ulid().'.start');
        File::ensureDirectoryExists(dirname($barrierPath));

        $processes = [
            $this->idempotencyWorker($barrierPath),
            $this->idempotencyWorker($barrierPath),
        ];

        try {
            foreach ($processes as $process) {
                $process->start();
            }

            File::put($barrierPath, 'start');

            $results = [];
            foreach ($processes as $process) {
                $process->wait();
                $this->assertTrue(
                    $process->isSuccessful(),
                    trim($process->getErrorOutput().' '.$process->getOutput()),
                );

                $result = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
                $this->assertIsArray($result);
                $results[] = $result;
            }

            $replayed = array_column($results, 'replayed');
            sort($replayed);
            $operationIds = array_column($results, 'operation_id');

            $this->assertSame([false, true], $replayed);
            $this->assertCount(1, array_unique($operationIds));
            $this->assertSame([201, 201], array_column($results, 'response_code'));
            $this->assertDatabaseCount('idempotency_keys', 1);
            $this->assertDatabaseHas('idempotency_keys', [
                'scope' => 'branch:concurrency',
                'operation' => 'sale.create',
                'key' => 'shared-request-key',
                'status' => 'completed',
            ]);
            $this->assertDatabaseCount('outbox_events', 1);
        } finally {
            File::delete($barrierPath);
            Artisan::call('migrate:fresh', ['--force' => true]);
        }
    }

    private function idempotencyWorker(string $barrierPath): Process
    {
        return new Process(
            [PHP_BINARY, '-r', $this->workerCode(), '--', $barrierPath],
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

while (! is_file($argv[1])) {
    usleep(1_000);
}

$result = $app->make(\App\Domain\Shared\Services\IdempotencyService::class)->execute(
    'branch:concurrency',
    'sale.create',
    'shared-request-key',
    ['total' => '10000.00'],
    function () use ($app): array {
        $event = $app->make(\App\Domain\Shared\Services\OutboxRecorder::class)->record(
            null,
            'test.idempotency_executed',
            'test_operation',
            'shared-request-key',
            ['total' => '10000.00'],
        );
        usleep(200_000);

        return ['operation_id' => $event->id];
    },
    responseCode: 201,
);

echo json_encode([
    'operation_id' => $result->payload['operation_id'],
    'response_code' => $result->responseCode,
    'replayed' => $result->replayed,
], JSON_THROW_ON_ERROR);
PHP;
    }
}
