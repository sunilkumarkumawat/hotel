<?php

namespace App\Http\Controllers;

use App\Helpers\Helper;
use App\Models\Branch\Branch;
use App\Models\FrontOffice\CheckIn;
use App\Models\Reservation\Reservation;
use App\Models\Reservation\ReservationRoom;
use Illuminate\Http\Request;
use Illuminate\View\View;


class PreRegCardController extends Controller
{
    public function index(Request $request): View
    {
        $branchId = Helper::getActiveBranchId();

        $filters = [
            'q' => $request->string('q')->toString(),
            'from' => $request->string('from')->toString() ?: today()->toDateString(),
            'to' => $request->string('to')->toString() ?: today()->addDays(6)->toDateString(),
        ];

        $arrivals = Reservation::query()
            ->where('branch_id', $branchId)
            ->whereNotIn('status', ['cancelled', 'no_show'])
            ->whereHas('rooms', fn ($q) => $q
                ->whereDate('arrival_date', '>=', $filters['from'])
                ->whereDate('arrival_date', '<=', $filters['to']))
            ->with(['rooms.category', 'rooms.type', 'rooms.plan', 'rooms.checkIns', 'company'])
            ->search($filters['q'])
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 15) ?: 15)
            ->withQueryString();

        return view('front-office.pre-reg-card', [
            'arrivals' => $arrivals,
            'filters' => $filters,
            'perPage' => $arrivals->perPage(),
            'branch' => Helper::activeBranch(),
        ]);
    }

    public function show(Reservation $reservation): View
    {
        abort_unless($reservation->branch_id === Helper::getActiveBranchId(), 404);

        $reservation->load([
            'rooms.category', 'rooms.type', 'rooms.plan', 'rooms.room',
            'company', 'bookedBy', 'visitPurpose', 'billingInstruction', 'country',
        ]);

        $branch = $this->branch();

        return view('front-office.reg-card', [
            'branch' => $branch,
            'terms' => $this->terms($branch),
            'card' => $this->card($reservation),
            'back' => route('front-office.pre-reg-card'),
        ]);
    }

    public function blank(): View
    {
        $branch = $this->branch();

        return view('front-office.reg-card', [
            'branch' => $branch,
            'terms' => $this->terms($branch),
            'card' => null,
            'back' => route('front-office.pre-reg-card'),
        ]);
    }
    private function branch(): Branch
    {
        $branch = Helper::activeBranch();

        abort_unless($branch, 404, 'No branch is selected.');

        return $branch;
    }

    /**
     * @return list<string>
     */
    private function terms(Branch $branch): array
    {
        return collect(preg_split('/\r\n|\r|\n/', (string) $branch->reg_card_terms))
            ->map(fn (string $line) => trim($line))
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function card(Reservation $reservation): array
    {
        $first = $reservation->rooms->first();

        return [
            'reservation_no' => $reservation->reservation_no,
            'name' => $reservation->guest_name,
            'address' => trim(collect([
                $reservation->address,
                $reservation->city?->name ?? null,
                $reservation->state?->name ?? null,
                $reservation->zip_code,
            ])->filter()->implode(', ')),
            'nationality' => $reservation->country?->name,
            'email' => $reservation->email,
            'mobile' => trim($reservation->mobile . ($reservation->mobile2 ? ', ' . $reservation->mobile2 : '')),
            'company' => $reservation->company?->name,
            'booked_by' => $reservation->bookedBy?->name,
            'company_gst_no' => $reservation->company_gst_no,
            'arrival_in_india' => null,
            'c_form_no' => null,

            'arrived_from' => $reservation->arrival_from,
            'departure_to' => $reservation->departure_to,
            'purpose' => $reservation->visitPurpose?->name,
            'duration' => $first ? $first->no_of_days . ' night(s)' : null,
            'arrival_at' => $this->stamp($first?->arrival_date?->toDateString(), $first?->arrival_time),
            'departure_at' => $this->stamp($first?->checkout_date?->toDateString(), $first?->checkout_time),
            'billing_instruction' => $reservation->billingInstruction?->name,

            'rooms' => $reservation->rooms->map(fn (ReservationRoom $row) => [
                'room_no' => $row->room_no ?: $this->allottedNumbers($row),
                'occupancy' => (int) $row->male + (int) $row->female + (int) $row->child,
                'category' => $row->category?->name ?? $row->type?->name,
                'plan' => $row->plan?->name,
                'male' => $row->male,
                'female' => $row->female,
                'child' => $row->child,
                'rate' => number_format((float) $row->room_rent, 2),
            ])->all(),
        ];
    }

    private function allottedNumbers(ReservationRoom $row): ?string
    {
        $numbers = $row->checkIns
            ->where('status', '!=', 'cancelled')
            ->map(fn (CheckIn $checkIn) => $checkIn->room?->room_no)
            ->filter();

        return $numbers->isNotEmpty()
            ? $numbers->implode(', ')
            : ((int) $row->no_of_rooms > 1 ? $row->no_of_rooms . ' rooms' : null);
    }

    private function stamp(?string $date, ?string $time): ?string
    {
        if (! $date) {
            return null;
        }

        return date('d/m/Y', strtotime($date)) . ($time ? ' ' . substr($time, 0, 5) : '');
    }
}
