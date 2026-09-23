<?php

namespace App\Support;

use RuntimeException;

/**
 * "That is not something you can do to this shift."
 *
 * Thrown by App\Support\Shifts when opening or closing a drawer would leave
 * the money in a state nobody could explain — a second open shift for the
 * same person, or a close on a shift somebody else has already closed.
 *
 * Its message is written for the cashier at the screen, so a controller can
 * flash it straight back. It is a class of its own so that a catch block
 * cannot mistake a database failure for a refused close.
 */
class ShiftRefused extends RuntimeException
{
}
