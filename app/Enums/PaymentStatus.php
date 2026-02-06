<?php

namespace App\Enums;

enum PaymentStatus: string
{
    case Pending = 'pending';
    case Paid = 'paid';
    case Failed = 'failed';
    case Refunded = 'refunded';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Gaida apmaksu',
            self::Paid => 'Apmaksāts',
            self::Failed => 'Neizdevās',
            self::Refunded => 'Atmaksāts',
        };
    }
}
