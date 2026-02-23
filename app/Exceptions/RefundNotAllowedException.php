<?php

namespace App\Exceptions;

use App\Models\Booking;
use RuntimeException;

class RefundNotAllowedException extends RuntimeException
{
    public function __construct(public Booking $booking)
    {
        $message = $booking->payment_status->value !== 'paid'
            ? 'Booking is not in a refundable state.'
            : 'Refund is not allowed less than 24 hours before the service.';

        parent::__construct($message);
    }
}
