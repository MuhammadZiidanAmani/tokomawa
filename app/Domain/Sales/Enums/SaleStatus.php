<?php

namespace App\Domain\Sales\Enums;

enum SaleStatus: string
{
    case Posting = 'posting';
    case Posted = 'posted';
}
