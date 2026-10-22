<?php

namespace App\Domain\Shared\Exceptions;

use Illuminate\Contracts\Debug\ShouldntReport;
use RuntimeException;

class IdempotencyInProgressException extends RuntimeException implements ShouldntReport
{
    public function __construct()
    {
        parent::__construct('Permintaan dengan idempotency key yang sama masih diproses.');
    }
}
