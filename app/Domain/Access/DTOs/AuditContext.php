<?php

namespace App\Domain\Access\DTOs;

use App\Domain\Organization\Models\Branch;
use App\Models\User;

class AuditContext
{
    public function __construct(
        public readonly ?User $actor,
        public readonly ?Branch $branch,
        public readonly ?string $reason = null,
        public readonly ?string $ipAddress = null,
        public readonly ?string $userAgent = null,
    ) {}
}
