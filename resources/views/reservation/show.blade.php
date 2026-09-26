@extends('layouts.app')

@section('title', 'Reservation ' . $reservation->reservation_no)

@php
    $tones = [
        'confirmed' => 'success', 'tentative' => 'warning', 'cancelled' => 'danger',
        'checked_in' => 'primary', 'checked_out' => 'info', 'no_show' => 'danger',
    ];

    $detail = fn ($label, $value) => ['label' => $label, 'value' => $value];

    $tripDetails = collect([
        $detail('Reservation type', \App\Models\Reservation\Reservation::TYPES[$reservation->reservation_type] ?? '—'),
        $detail('Booked on', $reservation->reservation_date->format('d M Y')),
        $detail('Arrival from', $reservation->arrival_from),
        $detail('Departure to', $reservation->departure_to),
        $detail('Transport', $reservation->transport_mode),
        $detail('Pick and drop', $reservation->pickDrop?->name),
        $detail('Visit purpose', $reservation->visitPurpose?->name),
        $detail('Voucher no.', $reservation->confirm_voucher_no),
    ]);

    $tradeDetails = collect([
        $detail('Booked by', $reservation->bookedBy?->name),
        $detail('Business market', $reservation->businessMarket?->name),
        $detail('Company', $reservation->company?->name),
        $detail('Company GST', $reservation->company_gst_no),
        $detail('Taken by', $reservation->employee?->name),
        $detail('Billing instruction', $reservation->billingInstruction?->name),
        $detail('Pay mode', $reservation->payMode?->name),
    ]);
@endphp

@section('content')
    <x-page-header
        :title="'Reservation ' . $reservation->reservation_no"
        :subtitle="$reservation->guest_name . ' · ' . $reservation->mobile"
        :crumbs="['Home' => url('/'), 'Reservations' => route('reservation.index'), $reservation->reservation_no]"
    >
        <x-slot:actions>
            @if ($reservation->isEditable())
                @canEdit('reservation/new-reservation')
                    <a href="{{ route('reservation.edit', $reservation) }}" class="nv-btn nv-btn-outline">
                        <x-icon name="pencil" /> Edit
                    </a>
                @endCanEdit

                @canDelete('reservation/new-reservation')
                    <button type="button" class="nv-btn nv-btn-ghost" data-toggle-row="cancel-box">
                        <x-icon name="x-circle" /> Cancel booking
                    </button>
                @endCanDelete
            @endif

            <a href="{{ route('reservation.index') }}" class="nv-btn nv-btn-ghost">Back</a>
        </x-slot:actions>
    </x-page-header>

    @if ($reservation->isCancelled())
        <div style="margin-bottom:18px">
            <x-alert tone="danger" title="Cancelled on {{ $reservation->cancelled_on?->format('d M Y') }}">
                {{ $reservation->cancel_reason ?: 'No reason recorded.' }}
            </x-alert>
        </div>
    @endif

    {{-- Cancel form, hidden until the button above is pressed --}}
    <div id="cancel-box" hidden style="margin-bottom:18px">
        <x-card title="Cancel this booking" subtitle="The rooms go straight back into the available pool.">
            <form method="POST" action="{{ route('reservation.cancel', $reservation) }}">
                @csrf
                <div class="nv-form-grid">
                    <x-field label="Reason" name="cancel_reason" required wide>
                        <x-input name="cancel_reason" placeholder="Guest called to cancel" />
                    </x-field>
                </div>

                <div class="nv-actions" style="justify-content:flex-end">
                    <button type="button" class="nv-btn nv-btn-ghost" data-toggle-row="cancel-box">Keep it</button>
                    <button type="submit" class="nv-btn nv-btn-danger">Cancel reservation</button>
                </div>
            </form>
        </x-card>
    </div>

    <div class="nv-grid nv-grid-4">
        <x-stat label="Rooms" :value="$reservation->rooms->sum('no_of_rooms')" icon="home" />
        <x-stat label="Net amount" :value="'₹' . number_format($reservation->net_amount, 0)" icon="wallet" tone="info"
                :caption="'incl. ₹' . number_format($reservation->tax_total, 0) . ' tax'" />
        <x-stat label="Advance paid" :value="'₹' . number_format($reservation->advance_paid, 0)" icon="credit-card" tone="success" />
        <x-stat label="Balance" :value="'₹' . number_format($reservation->balance, 0)"
                icon="alert" :tone="$reservation->balance > 0 ? 'warning' : 'success'" />
    </div>

    <div class="nv-grid nv-grid-main nv-mt">
        <div class="nv-grid">
            <x-card title="Rooms" flush>
                <x-slot:actions>
                    <x-badge :tone="$tones[$reservation->status] ?? null">
                        {{ \App\Models\Reservation\Reservation::STATUSES[$reservation->status] }}
                    </x-badge>
                </x-slot:actions>

                <div class="nv-table-wrap">
                    <table class="nv-table nv-table-compact">
                        <thead>
                            <tr>
                                <th>Arrival</th>
                                <th>Checkout</th>
                                <th class="is-num">Nights</th>
                                <th>Room</th>
                                <th>Type / Plan</th>
                                <th class="is-num">Rent</th>
                                <th class="is-num">Tax</th>
                                <th class="is-num">Net</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($reservation->rooms as $room)
                                <tr>
                                    <td class="nv-nowrap">
                                        {{ $room->arrival_date->format('d M Y') }}
                                        <span class="nv-sub">{{ substr((string) $room->arrival_time, 0, 5) }}</span>
                                    </td>
                                    <td class="nv-nowrap">
                                        {{ $room->checkout_date->format('d M Y') }}
                                        <span class="nv-sub">{{ substr((string) $room->checkout_time, 0, 5) }}</span>
                                    </td>
                                    <td class="is-num">{{ $room->no_of_days }}</td>
                                    <td>
                                        @if ($room->room_no)
                                            <strong>{{ $room->room_no }}</strong>
                                        @else
                                            <x-badge tone="warning">Not allotted</x-badge>
                                        @endif
                                    </td>
                                    <td>
                                        <strong>{{ $room->type?->name ?? '—' }}</strong>
                                        <span class="nv-sub">{{ $room->plan?->name ?? 'No plan' }}</span>
                                    </td>
                                    <td class="is-num">
                                        ₹{{ number_format($room->room_rent, 2) }}
                                        @if ($room->discount > 0)
                                            <span class="nv-sub">− ₹{{ number_format($room->discount, 2) }}</span>
                                        @endif
                                    </td>
                                    <td class="is-num">
                                        ₹{{ number_format($room->tax_amount, 2) }}
                                        <span class="nv-sub">{{ rtrim(rtrim(number_format($room->tax_percent, 2), '0'), '.') }}%</span>
                                    </td>
                                    <td class="is-num"><strong>₹{{ number_format($room->net_amount, 2) }}</strong></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-card>

            @if ($reservation->services->count())
                <x-card title="Services" flush>
                    <div class="nv-table-wrap">
                        <table class="nv-table nv-table-compact">
                            <thead>
                                <tr>
                                    <th>Service</th>
                                    <th class="is-num">Qty</th>
                                    <th class="is-num">Price</th>
                                    <th class="is-num">Tax</th>
                                    <th class="is-num">Total</th>
                                    <th>Remark</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($reservation->services as $service)
                                    <tr>
                                        <td><strong>{{ $service->service_name }}</strong></td>
                                        <td class="is-num">{{ rtrim(rtrim(number_format($service->qty, 2), '0'), '.') }}</td>
                                        <td class="is-num">₹{{ number_format($service->price, 2) }}</td>
                                        <td class="is-num">₹{{ number_format($service->tax_amount, 2) }}</td>
                                        <td class="is-num"><strong>₹{{ number_format($service->total_amount, 2) }}</strong></td>
                                        <td class="nv-muted">{{ $service->remark ?: '—' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </x-card>
            @endif

            <div class="nv-grid nv-grid-2">
                <x-card title="Trip">
                    <div class="nv-detail-list">
                        @foreach ($tripDetails as $item)
                            <div class="nv-detail-row">
                                <span>{{ $item['label'] }}</span>
                                <b>{{ $item['value'] ?: '—' }}</b>
                            </div>
                        @endforeach
                    </div>
                </x-card>

                <x-card title="Trade and billing">
                    <div class="nv-detail-list">
                        @foreach ($tradeDetails as $item)
                            <div class="nv-detail-row">
                                <span>{{ $item['label'] }}</span>
                                <b>{{ $item['value'] ?: '—' }}</b>
                            </div>
                        @endforeach
                    </div>
                </x-card>
            </div>

            @if ($reservation->remark || $reservation->special_remark)
                <x-card title="Remarks">
                    @if ($reservation->remark)
                        <p style="font-size:13.5px;line-height:1.7">{{ $reservation->remark }}</p>
                    @endif
                    @if ($reservation->special_remark)
                        <hr class="nv-hr" />
                        <p style="font-size:13.5px;line-height:1.7;color:var(--nv-text-2)">
                            <strong>Special:</strong> {{ $reservation->special_remark }}
                        </p>
                    @endif
                </x-card>
            @endif
        </div>

        <div class="nv-grid">
            <x-card title="Money">
                <div class="nv-total-list">
                    <div class="nv-total-row"><span>Room total</span><b>₹{{ number_format($reservation->room_total, 2) }}</b></div>
                    <div class="nv-total-row"><span>Service total</span><b>₹{{ number_format($reservation->service_total, 2) }}</b></div>
                    <div class="nv-total-row"><span>Discount</span><b>₹{{ number_format($reservation->discount_total, 2) }}</b></div>
                    <div class="nv-total-row"><span>Tax</span><b>₹{{ number_format($reservation->tax_total, 2) }}</b></div>
                    <div class="nv-total-row is-net"><span>NET AMOUNT</span><b>₹{{ number_format($reservation->net_amount, 2) }}</b></div>
                    <div class="nv-total-row"><span>Advance paid</span><b>− ₹{{ number_format($reservation->advance_paid, 2) }}</b></div>
                    <div class="nv-total-row is-net"><span>BALANCE</span><b>₹{{ number_format($reservation->balance, 2) }}</b></div>
                </div>
            </x-card>

            <x-card title="Advance Deposit Details">
                <div class="nv-feed">
                    @forelse ($reservation->deposits as $deposit)
                        <div class="nv-feed-item">
                            <span class="nv-matrix-icon">
                                <x-icon :name="$deposit->type === 'refund' ? 'refresh' : 'wallet'" />
                            </span>
                            <div class="nv-feed-body">
                                <p><b>{{ $deposit->type === 'refund' ? '− ' : '' }}₹{{ number_format($deposit->amount, 2) }}</b></p>
                                <span class="nv-feed-time">
                                    {{ $deposit->deposit_date->format('d M Y') }}
                                    · {{ $deposit->payMode?->name ?? 'No pay mode' }}
                                    {{ $deposit->reference_no ? '· ' . $deposit->reference_no : '' }}
                                </span>
                            </div>
                        </div>
                    @empty
                        <p class="nv-muted" style="font-size:13.5px">Nothing taken yet.</p>
                    @endforelse
                </div>

                @unless ($reservation->isCancelled())
                    @canEdit('reservation/advance-deposit')
                        <hr class="nv-hr" />

                        <form method="POST" action="{{ route('reservation.deposit', $reservation) }}">
                            @csrf

                            <div class="nv-form-grid">
                                <x-field label="Amount" name="amount" required>
                                    <x-input name="amount" type="number" step="0.01" min="0.01" placeholder="0.00" />
                                </x-field>

                                <x-field label="Date" name="deposit_date" required>
                                    <x-input name="deposit_date" type="date" :value="now()->toDateString()" />
                                </x-field>

                                <x-field label="Pay mode" name="pay_mode_id">
                                    <x-select name="pay_mode_id"
                                              :options="\App\Models\Master\PayMode::forBranch()->active()->pluck('name', 'id')->all()"
                                              placeholder="Choose…" />
                                </x-field>

                                <x-field label="Kind" name="type">
                                    <x-select name="type" :options="['deposit' => 'Deposit', 'refund' => 'Refund']" selected="deposit" />
                                </x-field>

                                <x-field label="Reference no." name="reference_no" wide>
                                    <x-input name="reference_no" placeholder="UTR / cheque / card slip" />
                                </x-field>
                            </div>

                            <div class="nv-actions" style="justify-content:flex-end">
                                <button type="submit" class="nv-btn nv-btn-primary nv-btn-sm">
                                    <x-icon name="plus" /> Record
                                </button>
                            </div>
                        </form>
                    @endCanEdit
                @endunless
            </x-card>
        </div>
    </div>
@endsection
