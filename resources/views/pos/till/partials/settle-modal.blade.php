{{--
    Taking the money.

    Three ways out of an order and they are genuinely different acts, so they
    are three panels rather than one form with a "type" dropdown: cash and
    cards, signed to a room, or no charge against a named reason.

    The payment panel takes three lines because a guest paying part cash and
    part card is ordinary, and recording that as one "mixed" entry is how a
    collections report stops being able to answer anything.
--}}

@php
    $balance = $invoice ? $invoice->balance() : (float) $order->net_amount;
    $canRoom = $stay || $order->check_in_id;
@endphp

@canEdit('point-of-sale/pos')
    <div class="nv-modal-backdrop" id="settle" data-modal="settle" data-due="{{ number_format($balance, 2, '.', '') }}">
        <div class="nv-modal nv-modal-wide" role="dialog" aria-modal="true" aria-labelledby="settle-title">
            <div class="nv-modal-head">
                <strong id="settle-title">Settle ₹ {{ number_format($balance, 2) }}</strong>
                <a href="#" class="nv-icon-btn" data-modal-close aria-label="Close">
                    <x-icon name="x" />
                </a>
            </div>

            {{--
                Links rather than buttons, pointing at the panels' own ids. With
                a script they are intercepted and behave as tabs; without one
                they still reach all three panels, because "no charge" and "sign
                to room" must not be unreachable on a till whose JavaScript is
                blocked.
            --}}
            <div class="nv-seg" role="tablist" aria-label="How it is being paid">
                <a href="#settle" class="nv-seg-btn is-on" data-settle-tab="pay" role="tab" aria-selected="true">
                    <x-icon name="wallet" /> Payment
                </a>

                @if ($canRoom)
                    <a href="#settle-room" class="nv-seg-btn" data-settle-tab="room" role="tab" aria-selected="false">
                        <x-icon name="home" /> Sign to room
                    </a>
                @endif

                <a href="#settle-nc" class="nv-seg-btn" data-settle-tab="nc" role="tab" aria-selected="false">
                    <x-icon name="alert" /> No charge
                </a>
            </div>

            {{-- ── Money ──────────────────────────────────────────────── --}}
            <form method="POST" action="{{ route('point-of-sale.pos.order.settle', $order->id) }}"
                  data-settle-panel="pay" class="nv-settle-panel is-active">
                @csrf
                <input type="hidden" name="how" value="pay" />

                @if ($payModes->isEmpty())
                    <x-alert tone="warning" title="No pay modes set up">
                        Add Cash, Card and UPI under Masters → Pay Mode first, or the money cannot be
                        recorded against anything.
                    </x-alert>
                @else
                    <table class="nv-table nv-settle-table">
                        <thead>
                            <tr>
                                <th>Mode</th>
                                <th style="width:150px">Amount</th>
                                <th style="width:170px">Reference</th>
                            </tr>
                        </thead>
                        <tbody>
                            @for ($row = 0; $row < 3; $row++)
                                <tr>
                                    <td>
                                        <select name="payments[{{ $row }}][pay_mode_id]" class="nv-select">
                                            <option value="">{{ $row === 0 ? 'Choose' : '—' }}</option>
                                            @foreach ($payModes as $mode)
                                                <option value="{{ $mode->id }}"
                                                        @selected($row === 0 && str_contains(strtolower($mode->name), 'cash'))>
                                                    {{ $mode->name }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td>
                                        <div class="nv-input-group">
                                            <span class="nv-input-addon">₹</span>
                                            <input type="number" name="payments[{{ $row }}][amount]"
                                                   step="0.01" min="0" class="nv-input"
                                                   value="{{ $row === 0 ? number_format($balance, 2, '.', '') : '' }}"
                                                   data-settle-amount />
                                        </div>
                                    </td>
                                    <td>
                                        <input type="text" name="payments[{{ $row }}][reference_no]" maxlength="60"
                                               class="nv-input" placeholder="Card / UPI ref" />
                                    </td>
                                </tr>
                            @endfor
                        </tbody>
                    </table>

                    <p class="nv-help" data-settle-sum>
                        Anything short of the full amount leaves the bill open and puts it on
                        Unsettled Invoices, which is where it should be.
                    </p>

                    <div class="nv-actions" style="justify-content:flex-end;margin-top:16px">
                        <a href="#" class="nv-btn nv-btn-ghost" data-modal-close>Cancel</a>
                        <button type="submit" class="nv-btn nv-btn-primary">
                            <x-icon name="check" /> Take payment
                        </button>
                    </div>
                @endif
            </form>

            {{-- ── The guest's folio ──────────────────────────────────── --}}
            @if ($canRoom)
                <form method="POST" action="{{ route('point-of-sale.pos.order.settle', $order->id) }}"
                      id="settle-room" data-settle-panel="room" class="nv-settle-panel">
                    @csrf
                    <input type="hidden" name="how" value="room" />
                    <input type="hidden" name="check_in_id" value="{{ $stay?->id ?? $order->check_in_id }}" />

                    <p>
                        This becomes one line on
                        <strong>{{ $stay?->guest_name ?: 'the guest' }}</strong>’s folio for
                        <strong>room {{ $stay?->room?->room_no ?: '—' }}</strong>, carrying the bill number,
                        and leaves with them at checkout.
                    </p>

                    <x-alert tone="info" title="It leaves the restaurant's books">
                        Once signed, this is the front desk's money to collect. It stops showing as
                        owed here and starts showing on the guest's bill.
                    </x-alert>

                    <div class="nv-actions" style="justify-content:flex-end;margin-top:16px">
                        <a href="#" class="nv-btn nv-btn-ghost" data-modal-close>Cancel</a>
                        <button type="submit" class="nv-btn nv-btn-primary">
                            <x-icon name="home" /> Sign to room
                        </button>
                    </div>
                </form>
            @endif

            {{-- ── No charge ──────────────────────────────────────────── --}}
            <form method="POST" action="{{ route('point-of-sale.pos.order.settle', $order->id) }}"
                  id="settle-nc" data-settle-panel="nc" class="nv-settle-panel">
                @csrf
                <input type="hidden" name="how" value="nc" />

                @if ($ncTypes->isEmpty())
                    <x-alert tone="warning" title="No NC types set up">
                        Food that leaves without being paid for has to be named. Add the reasons under
                        Setup → NC Types.
                    </x-alert>
                @else
                    <x-field label="Reason" name="nc_type_id" required>
                        <select name="nc_type_id" id="nc_type_id" class="nv-select" required data-nc-type>
                            <option value="">Choose a reason</option>
                            @foreach ($ncTypes as $type)
                                <option value="{{ $type->id }}" data-needs-dept="{{ $type->requires_department ? 1 : 0 }}">
                                    {{ $type->name }}
                                </option>
                            @endforeach
                        </select>
                    </x-field>

                    <x-field label="Whose budget" name="nc_department_id">
                        <select name="nc_department_id" id="nc_department_id" class="nv-select" data-nc-dept>
                            <option value="">—</option>
                            @foreach ($departments as $id => $name)
                                <option value="{{ $id }}">{{ $name }}</option>
                            @endforeach
                        </select>
                    </x-field>

                    <p class="nv-help">
                        The whole order becomes no-charge and its value is recorded as a discount, so it
                        still shows in what the kitchen produced — it just is not money.
                    </p>

                    <div class="nv-actions" style="justify-content:flex-end;margin-top:16px">
                        <a href="#" class="nv-btn nv-btn-ghost" data-modal-close>Cancel</a>
                        <button type="submit" class="nv-btn nv-btn-primary">
                            <x-icon name="check" /> Settle as no charge
                        </button>
                    </div>
                @endif
            </form>
        </div>
    </div>
@endCanEdit
