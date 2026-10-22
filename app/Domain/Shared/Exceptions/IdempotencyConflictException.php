<?php

namespace App\Domain\Shared\Exceptions;

use Illuminate\Contracts\Debug\ShouldntReport;
use RuntimeException;

class IdempotencyConflictException extends RuntimeException implements ShouldntReport
{
    public function __construct()
    {
        parent::__construct('Idempotency key telah digunakan dengan payload berbeda.');
    }
}
