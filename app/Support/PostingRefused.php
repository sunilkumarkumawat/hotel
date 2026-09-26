<?php

namespace App\Support;

use RuntimeException;

/**
 * "That posting is not going into the books."
 *
 * Thrown by App\Support\Vouchers when something would have stored a voucher
 * that is wrong — most often one that does not balance. It carries a message
 * written for the clerk standing at the screen, not for a log file, so a
 * controller can catch it and flash it straight back.
 *
 * It is a class of its own rather than a plain RuntimeException so that a
 * controller's catch block cannot quietly swallow a genuine database failure
 * and tell the clerk their voucher was unbalanced.
 */
class PostingRefused extends RuntimeException
{
}
