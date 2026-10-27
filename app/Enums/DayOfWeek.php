<?php

namespace App\Enums;

enum DayOfWeek: int
{
    case Monday = 1;
    case Tuesday = 2;
    case Wednesday = 3;
    case Thursday = 4;
    case Friday = 5;
    case Saturday = 6;
    case Sunday = 7;

    public function label(): string
    {
        return match ($this) {
            self::Monday => 'Pirmdiena',
            self::Tuesday => 'Otrdiena',
            self::Wednesday => 'Trešdiena',
            self::Thursday => 'Ceturtdiena',
            self::Friday => 'Piektdiena',
            self::Saturday => 'Sestdiena',
            self::Sunday => 'Svētdiena',
        };
    }

    /**
     * The standard Latvian abbreviation of the day, as printed on calendars.
     */
    public function shortLabel(): string
    {
        return match ($this) {
            self::Monday => 'P',
            self::Tuesday => 'O',
            self::Wednesday => 'T',
            self::Thursday => 'C',
            self::Friday => 'Pk',
            self::Saturday => 'S',
            self::Sunday => 'Sv',
        };
    }
}
