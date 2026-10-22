<?php

namespace App\Domain\Shared\DTOs;

class IdempotencyResult
{
    /** @param array<string, mixed> $payload */
    public function __construct(
        public readonly array $payload,
        public readonly int $responseCode,
        public readonly bool $replayed,
    ) {}
}
