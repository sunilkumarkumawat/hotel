<?php

namespace App\Support;

use App\Models\FrontOffice\CheckIn;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The two registers an Indian hotel keeps for somebody other than the guest:
 * Form C for the FRRO, and the daily guest register for the local police.
 *
 * Both are the same underlying fact — who slept here, and how do we know it
 * was them — asked by two authorities who want it in two shapes. So the data
 * is read from `check_ins` and `check_in_pax` and arranged twice, rather than
 * written down twice and allowed to disagree.
 *
 * Masking lives here too. An ID number is the most sensitive thing a hotel
 * holds about a guest, and it is displayed on screens that anybody on the desk
 * can open, so the default everywhere is the masked form and the full number
 * is a separate permission.
 */
class Compliance
{
    /** ID documents an Indian hotel actually sees. */
    public const ID_TYPES = [
        'aadhaar' => 'Aadhaar',
        'passport' => 'Passport',
        'driving_licence' => 'Driving Licence',
        'voter_id' => 'Voter ID',
        'pan' => 'PAN',
        'other' => 'Other',
    ];

    /** Why a foreign guest says they have come. The FRRO's own list. */
    public const VISIT_PURPOSES = [
        'Tourism', 'Business', 'Conference', 'Employment', 'Study',
        'Medical', 'Transit', 'Visiting family or friends', 'Other',
    ];

    /*
    |--------------------------------------------------------------------------
    | Masking
    |--------------------------------------------------------------------------
    */

    /**
     * An ID number as a screen should show it.
     *
     * The last four characters survive, because that is what a guest is asked
     * to confirm at the desk and what makes a register auditable; everything
     * before them becomes bullets. An Aadhaar is grouped in fours on the way
     * out, since that is how it is printed and how somebody checks it.
     *
     * A number short enough that masking would leave nothing useful is masked
     * entirely rather than half-shown — four of six characters is not privacy.
     */
    public static function mask(?string $number, ?string $type = null): string
    {
        $clean = strtoupper(preg_replace('/\s+/', '', (string) $number));

        if ($clean === '') {
            return '—';
        }

        if (strlen($clean) <= 4) {
            return str_repeat('•', strlen($clean));
        }

        $tail = substr($clean, -4);
        $masked = str_repeat('•', strlen($clean) - 4) . $tail;

        /*
         * Grouped in fours, by CHARACTER and not by byte. The bullet is three
         * bytes of UTF-8, so chunk_split() would cut one in half and hand the
         * screen a row of replacement glyphs.
         */
        if ($type === 'aadhaar' && strlen($clean) === 12) {
            return implode(' ', mb_str_split($masked, 4));
        }

        return $masked;
    }

    /**
     * The full number, but only for somebody allowed to see it.
     *
     * The permission is checked here rather than in each view, so a new screen
     * that forgets to ask gets the masked form by default. Failing closed is
     * the only sensible direction for this one.
     */
    public static function reveal(?string $number, ?string $type = null, string $permission = 'compliance/police-register'): string
    {
        if (! $number) {
            return '—';
        }

        return can_do($permission, 'delete')
            ? strtoupper(trim($number))
            : self::mask($number, $type);
    }

    public static function idLabel(?string $type): string
    {
        return $type ? (self::ID_TYPES[$type] ?? ucfirst(str_replace('_', ' ', $type))) : '—';
    }

    /*
    |--------------------------------------------------------------------------
    | The police register
    |--------------------------------------------------------------------------
    */

    /**
     * Everybody who slept in the building on a given night.
     *
     * One row per PERSON, not per room — the register the station wants lists
     * names, and a family of four is four lines. The person the booking is in
     * the name of comes first in each room, then the people on the pax list,
     * which is the order a constable reads them in.
     *
     * A stay counts for the night if it had arrived by that date and had not
     * departed before it: `checkin_date <= D` and `departure > D`. A guest who
     * checked out on the morning of D did not sleep there on the night of D.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public static function register(int $branchId, string $date, array $filters = []): Collection
    {
        $stays = DB::table('check_ins as ci')
            ->leftJoin('rooms as r', 'r.id', '=', 'ci.room_id')
            ->leftJoin('countries as c', 'c.id', '=', 'ci.nationality_id')
            ->where('ci.branch_id', $branchId)
            ->where('ci.status', '!=', 'cancelled')
            ->whereDate('ci.checkin_date', '<=', $date)
            ->whereRaw('COALESCE(ci.actual_checkout_date, ci.expected_checkout_date) > ?', [$date])
            ->when($filters['foreign'] ?? false, fn ($q) => $q->where('ci.is_foreign', true))
            ->when($filters['q'] ?? null, fn ($q, $term) => $q->where(function ($q) use ($term) {
                $q->where('ci.guest_name', 'like', "%{$term}%")
                    ->orWhere('ci.mobile', 'like', "%{$term}%")
                    ->orWhere('r.room_no', 'like', "%{$term}%");
            }))
            ->orderBy('r.room_no')
            ->orderBy('ci.id')
            ->get([
                'ci.id', 'ci.folio_no', 'ci.guest_name', 'ci.mobile', 'ci.checkin_date', 'ci.checkin_time',
                'ci.expected_checkout_date', 'ci.actual_checkout_date', 'ci.id_type', 'ci.id_number',
                'ci.is_foreign', 'ci.male', 'ci.female', 'ci.child',
                'r.room_no', 'c.name as nationality',
            ]);

        $pax = DB::table('check_in_pax')
            ->whereIn('check_in_id', $stays->pluck('id')->all() ?: [0])
            ->orderBy('id')
            ->get()
            ->groupBy('check_in_id');

        $rows = collect();

        foreach ($stays as $stay) {
            $rows->push([
                'room_no' => $stay->room_no ?: '—',
                'folio_no' => $stay->folio_no,
                'name' => $stay->guest_name,
                'relation' => 'Self',
                'age' => null,
                'gender' => null,
                'mobile' => $stay->mobile,
                'nationality' => $stay->nationality ?: ($stay->is_foreign ? 'Foreign national' : 'Indian'),
                'is_foreign' => (bool) $stay->is_foreign,
                'id_type' => $stay->id_type,
                'id_number' => $stay->id_number,
                'arrived' => $stay->checkin_date,
                'arrived_time' => $stay->checkin_time,
                'departs' => $stay->actual_checkout_date ?: $stay->expected_checkout_date,
                'check_in_id' => (int) $stay->id,
                'pax_id' => null,
            ]);

            foreach ($pax[$stay->id] ?? [] as $person) {
                $rows->push([
                    'room_no' => $stay->room_no ?: '—',
                    'folio_no' => $stay->folio_no,
                    'name' => $person->name,
                    'relation' => $person->relation ?: 'Accompanying',
                    'age' => $person->age,
                    'gender' => $person->gender,
                    'mobile' => null,
                    'nationality' => $stay->nationality ?: ($stay->is_foreign ? 'Foreign national' : 'Indian'),
                    'is_foreign' => (bool) $stay->is_foreign,
                    'id_type' => $person->id_type,
                    'id_number' => $person->id_number,
                    'arrived' => $stay->checkin_date,
                    'arrived_time' => $stay->checkin_time,
                    'departs' => $stay->actual_checkout_date ?: $stay->expected_checkout_date,
                    'check_in_id' => (int) $stay->id,
                    'pax_id' => (int) $person->id,
                ]);
            }
        }

        return $rows;
    }

    /**
     * What the register says about itself, for the top of the page.
     *
     * The count of people with no ID recorded is the number that matters:
     * it is the one a station officer will pick on, and the only one the desk
     * can still do something about.
     *
     * @return array<string, int>
     */
    public static function registerSummary(Collection $rows): array
    {
        return [
            'people' => $rows->count(),
            'rooms' => $rows->pluck('check_in_id')->unique()->count(),
            'foreign' => $rows->where('is_foreign', true)->count(),
            'no_id' => $rows->filter(fn (array $r) => trim((string) $r['id_number']) === '')->count(),
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Form C
    |--------------------------------------------------------------------------
    */

    /**
     * Foreign guests in the house who have no Form C filed yet.
     *
     * Deliberately "in the house or arrived recently" rather than "ever":
     * a form for a guest who left in March is a thing to note, not a thing to
     * chase, and a list that never empties is a list nobody reads.
     *
     * @return Collection<int, object>
     */
    public static function formCPending(int $branchId, int $days = 30): Collection
    {
        $since = CarbonImmutable::parse(today()->toDateString())->subDays($days)->toDateString();

        return collect(DB::table('check_ins as ci')
            ->leftJoin('rooms as r', 'r.id', '=', 'ci.room_id')
            ->leftJoin('countries as c', 'c.id', '=', 'ci.nationality_id')
            ->where('ci.branch_id', $branchId)
            ->where('ci.status', '!=', 'cancelled')
            ->where('ci.is_foreign', true)
            ->whereDate('ci.checkin_date', '>=', $since)
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))->from('form_c_entries as f')
                    ->whereColumn('f.check_in_id', 'ci.id')
                    ->whereNull('f.check_in_pax_id');
            })
            ->orderByDesc('ci.checkin_date')
            ->get([
                'ci.id', 'ci.folio_no', 'ci.guest_name', 'ci.mobile', 'ci.checkin_date',
                'ci.expected_checkout_date', 'ci.actual_checkout_date', 'ci.status',
                'r.room_no', 'c.name as nationality',
            ]));
    }

    /**
     * Everything a Form C can be pre-filled with from what the hotel already
     * knows, so the desk types the passport and the visa and nothing else.
     *
     * @return array<string, mixed>
     */
    public static function prefillFormC(CheckIn $checkIn): array
    {
        $reservation = $checkIn->reservation;
        $guest = $checkIn->guest;
        $branch = \App\Helpers\Helper::activeBranch();

        return [
            'name' => $checkIn->guest_name,
            'nationality_id' => $checkIn->nationality_id ?: $reservation?->country_id,
            'date_of_birth' => $guest?->dob?->toDateString() ?: $reservation?->dob?->toDateString(),
            'sex' => $guest?->gender ?: $reservation?->gender,
            'passport_no' => $checkIn->id_type === 'passport' ? $checkIn->id_number : null,
            'arrived_from' => $reservation?->arrival_from,
            'next_destination' => $reservation?->departure_to,
            'purpose_of_visit' => $reservation?->visitPurpose?->name,
            'permanent_address' => trim((string) ($guest?->address ?: $reservation?->address)),
            // Where they are staying in India is this hotel, and the desk
            // should not have to type its own address.
            'address_in_india' => trim(implode(', ', array_filter([
                $branch?->legal_name ?: $branch?->branch_name,
                $branch?->address,
                $branch?->pin_code,
            ]))),
            'next_destination_on' => $checkIn->departsOn(),
        ];
    }

    /**
     * Is this stay a foreign guest's?
     *
     * India is read from the countries table by name rather than by a fixed id,
     * because that table is seeded per installation and nobody should have to
     * know which row India landed on.
     */
    public static function isForeignCountry(?int $countryId): bool
    {
        if (! $countryId) {
            return false;
        }

        $name = DB::table('countries')->where('id', $countryId)->value('name');

        return $name !== null && ! in_array(strtolower(trim($name)), ['india', 'bharat'], true);
    }
}
