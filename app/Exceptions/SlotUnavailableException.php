<?php

namespace App\Exceptions;

use RuntimeException;

class SlotUnavailableException extends RuntimeException
{
    public function __construct(string $message = 'The requested class can no longer be booked.')
    {
        parent::__construct($message);
    }
}
