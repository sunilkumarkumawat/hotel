<?php

namespace App\Support\Website;

use RuntimeException;

/**
 * Thrown when a room type no longer has enough rooms free for the dates
 * asked — either while quoting a public booking, or (rarely, and more
 * seriously) during the locked re-check that runs right before a paid
 * booking is turned into a real reservation. See BookingService.
 */
class RoomsUnavailableException extends RuntimeException
{
    public function __construct(public readonly int $left, string $message)
    {
        parent::__construct($message);
    }
}
