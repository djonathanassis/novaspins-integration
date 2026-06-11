<?php

declare(strict_types=1);

namespace App\Enums;

enum TransactionType: string
{
    case Bet = 'bet';
    case Win = 'win';
    case Rollback = 'rollback';
}
