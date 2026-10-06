<?php

namespace App\Enums;

enum SlotStatus: string
{
    case Available = 'available';
    case Full = 'full';
    case Past = 'past';
    case Cancelled = 'cancelled';
    case Unavailable = 'unavailable';
}
