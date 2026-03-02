<?php

namespace App\Enums;

enum AttendanceStatus: string
{
    case Pending = 'pending';
    case Attended = 'attended';
    case Missed = 'missed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Nav apmeklēts',
            self::Attended => 'Apmeklēts',
            self::Missed => 'Neieradās',
        };
    }
}
