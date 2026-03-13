<?php

namespace App\Enums;

enum MembershipTier: string
{
    case FourSessions = 'four_sessions';
    case NineSessions = 'nine_sessions';

    public function label(): string
    {
        return match ($this) {
            self::FourSessions => '4 nodarbības mēnesī',
            self::NineSessions => '9 nodarbības mēnesī',
        };
    }

    public function sessionCount(): int
    {
        return match ($this) {
            self::FourSessions => 4,
            self::NineSessions => 9,
        };
    }
}
