<?php

namespace App\Domain\Inventory\Enums;

enum StockAdjustmentStatus: string
{
    case PendingApproval = 'pending_approval';
    case Approved = 'approved';
    case Posted = 'posted';
    case Rejected = 'rejected';
}
