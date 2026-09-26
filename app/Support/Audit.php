<?php

namespace App\Support;

use App\Helpers\Helper;
use App\Models\Audit\ActivityLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * Who changed what, and when.
 *
 * Three rules, and they are the whole design:
 *
 *   **It never breaks the thing it is watching.** Every write goes through
 *   rescue(). A hotel that cannot take a payment because the audit table is
 *   full has been made worse by its audit trail, not better.
 *
 *   **It writes names, not ids.** The user's name and the row's label are
 *   copied into the log. Deleting a user must not blank a year of history,
 *   and "Bill INV-1-0042" is what somebody is actually looking for.
 *
 *   **It only records what changed.** An update row holds the columns that
 *   moved and their before and after — not a copy of the model. A log that
 *   stores everything is one nobody can read, and it is how audit tables end
 *   up bigger than the database they are watching.
 */
class Audit
{
    /**
     * Which part of the hotel a model belongs to.
     *
     * Keyed by class name without its namespace, because that is the part
     * that stays put — the models have been moved between folders before.
     */
    private const AREAS = [
        'Bill' => 'money',
        'Settlement' => 'money',
        'AdvanceDeposit' => 'money',
        'FolioCharge' => 'money',
        'PosInvoice' => 'money',
        'PosPayment' => 'money',
        'Voucher' => 'money',
        'PettyCashPayment' => 'money',
        'PettyCashReceipt' => 'money',
        'CashierShift' => 'money',

        'CheckIn' => 'stay',
        'Reservation' => 'stay',
        'ReservationRoom' => 'stay',
        'Guest' => 'stay',
        'RoomBlock' => 'stay',
        'RoomTransfer' => 'stay',

        'RateRule' => 'rates',
        'RatePlan' => 'rates',
        'RateSeason' => 'rates',
        'Service' => 'rates',
        'TaxMaster' => 'rates',

        'StoreDoc' => 'stock',
        'StoreItem' => 'stock',
        'Recipe' => 'stock',

        'User' => 'access',
        'UserPermission' => 'access',
        'Role' => 'access',
        'Branch' => 'access',

        'BusinessDay' => 'system',
    ];

    /** Columns nobody should ever see in a log, whatever the model. */
    private const NEVER = [
        'password', 'remember_token', 'created_at', 'updated_at', 'api_token',
    ];

    /**
     * Record something that happened to a model.
     *
     * On an update this reads the model's own dirty tracking, so it has to be
     * called from inside the `updated` event — afterwards Eloquent has synced
     * the original values away and there is nothing left to compare.
     */
    public static function on(Model $subject, string $action, ?string $summary = null, array $extra = []): void
    {
        rescue(function () use ($subject, $action, $summary, $extra) {
            $changes = $action === 'updated' ? self::diff($subject) : [];

            // An update that changed nothing a person can see is not an event.
            if ($action === 'updated' && $changes === []) {
                return;
            }

            $label = method_exists($subject, 'auditLabel')
                ? (string) $subject->auditLabel()
                : self::guessLabel($subject);

            self::write([
                'action' => $action,
                'area' => $extra['area'] ?? self::areaFor($subject),
                'subject_type' => $subject::class,
                'subject_id' => $subject->getKey(),
                'subject_label' => Str::limit($label, 250, ''),
                'summary' => $summary ?: self::sentence($action, $subject, $label),
                'changes' => $changes ?: null,
                'branch_id' => $extra['branch_id'] ?? self::branchOf($subject),
            ]);
        }, null, false);
    }

    /**
     * Record something that has no model behind it — a sign-in, an export,
     * a night audit, a shift close.
     */
    public static function note(string $action, string $summary, array $extra = []): void
    {
        rescue(function () use ($action, $summary, $extra) {
            self::write(array_merge([
                'action' => $action,
                'area' => 'system',
                'summary' => $summary,
            ], $extra));
        }, null, false);
    }

    /**
     * The row itself.
     *
     * Everything a request can tell us is filled in here rather than by the
     * caller, so a log written from a controller and one written from a
     * console command carry the same fields.
     */
    private static function write(array $row): void
    {
        $user = Auth::user();
        $request = rescue(fn () => request(), null, false);

        $branch = $row['branch_id'] ?? null;

        if ($branch === null) {
            $branch = rescue(fn () => (int) Helper::getActiveBranchId(), null, false) ?: null;
        }

        ActivityLog::create([
            'branch_id' => $branch,
            'user_id' => $row['user_id'] ?? $user?->user_id,
            'user_name' => $row['user_name'] ?? ($user?->name ?: 'System'),
            'action' => $row['action'],
            'area' => $row['area'] ?? 'system',
            'subject_type' => $row['subject_type'] ?? null,
            'subject_id' => $row['subject_id'] ?? null,
            'subject_label' => $row['subject_label'] ?? null,
            'summary' => Str::limit((string) ($row['summary'] ?? ''), 250, ''),
            'changes' => $row['changes'] ?? null,
            'ip' => $row['ip'] ?? rescue(fn () => $request?->ip(), null, false),
            'agent' => Str::limit((string) rescue(fn () => $request?->userAgent(), '', false), 250, ''),
            'url' => Str::limit((string) rescue(fn () => $request?->fullUrl(), '', false), 250, ''),
            'method' => rescue(fn () => $request?->method(), null, false),
            'happened_at' => now(),
        ]);
    }

    /**
     * What actually moved, and from what to what.
     *
     * Money columns are compared as numbers: `12.00` and `12` are the same
     * amount, and a log full of rows saying a price changed from 12.00 to 12
     * is a log people learn to ignore.
     */
    public static function diff(Model $subject): array
    {
        $hidden = array_merge(
            self::NEVER,
            method_exists($subject, 'auditHidden') ? $subject->auditHidden() : []
        );

        $out = [];

        foreach ($subject->getChanges() as $column => $new) {
            if (in_array($column, $hidden, true)) {
                continue;
            }

            $old = $subject->getOriginal($column);

            if (is_numeric($old) && is_numeric($new) && (float) $old === (float) $new) {
                continue;
            }

            if ((string) $old === (string) $new) {
                continue;
            }

            $out[$column] = [
                'from' => self::scalar($old),
                'to' => self::scalar($new),
            ];
        }

        return $out;
    }

    /** JSON columns and objects are flattened; the trail is read, not replayed. */
    private static function scalar(mixed $value): mixed
    {
        if ($value === null || is_bool($value) || is_int($value) || is_float($value)) {
            return $value;
        }

        if (is_array($value)) {
            return Str::limit((string) json_encode($value), 200, '');
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        return Str::limit((string) $value, 200, '');
    }

    private static function areaFor(Model $subject): string
    {
        return self::AREAS[class_basename($subject)] ?? 'system';
    }

    /** A branch column if the model has one — most do, a few do not. */
    private static function branchOf(Model $subject): ?int
    {
        $branch = $subject->getAttribute('branch_id');

        return $branch ? (int) $branch : null;
    }

    /**
     * Something to call the row.
     *
     * In order of how much it tells a human: the document number, the name,
     * then the key. A model that wants better says so with auditLabel().
     */
    private static function guessLabel(Model $subject): string
    {
        foreach (['doc_no', 'invoice_no', 'bill_no', 'voucher_no', 'booking_no', 'order_no', 'shift_no', 'folio_no', 'name', 'guest_name', 'username', 'particulars'] as $column) {
            $value = $subject->getAttribute($column);

            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        return class_basename($subject) . ' #' . $subject->getKey();
    }

    private static function sentence(string $action, Model $subject, string $label): string
    {
        $what = Str::headline(class_basename($subject));

        return match ($action) {
            'created' => $what . ' ' . $label . ' added',
            'updated' => $what . ' ' . $label . ' changed',
            'deleted' => $what . ' ' . $label . ' deleted',
            default => $what . ' ' . $label . ' ' . str_replace('_', ' ', $action),
        };
    }
}
