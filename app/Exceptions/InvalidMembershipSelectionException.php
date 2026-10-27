<?php

namespace App\Exceptions;

use RuntimeException;

class InvalidMembershipSelectionException extends RuntimeException
{
    public function __construct(string $message = 'The selected membership or its sessions are not valid.')
    {
        parent::__construct($message);
    }
}
