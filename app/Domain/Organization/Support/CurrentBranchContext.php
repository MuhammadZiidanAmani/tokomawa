<?php

namespace App\Domain\Organization\Support;

use App\Domain\Organization\Models\Branch;

class CurrentBranchContext
{
    private ?Branch $branch = null;

    public function set(?Branch $branch): void
    {
        $this->branch = $branch;
    }

    public function get(): ?Branch
    {
        return $this->branch;
    }
}
