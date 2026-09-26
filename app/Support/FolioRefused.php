<?php

namespace App\Support;

use RuntimeException;

/**
 * "That folio is not being posted."
 *
 * Thrown by App\Support\Folio::postRoomCharges() when a stay's dates would
 * make it post an implausible number of nights — almost always a mistyped
 * year on an expected checkout date. It carries a message written for the
 * clerk standing at the screen, not for a log file, so a controller can catch
 * it and flash it straight back.
 *
 * It is a class of its own, the same way {@see PostingRefused} is, so a
 * controller's catch block cannot quietly swallow a genuine database failure
 * and tell the clerk their dates need fixing.
 */
class FolioRefused extends RuntimeException
{
}
