{{--
    A room-wise report, room by room.

    Both reports read the same way — "which room, and how much" — so they share
    this table and differ only in the four columns each puts in the middle.

    Expects: $rows (grouped by room), $grand, and $columns — a list of
    [heading, closure] pairs for the middle columns.
--}}

<div class="nv-mt">
    <x-card flush>
        <x-slot:title>{{ $grand['rooms'] }} room{{ $grand['rooms'] === 1 ? '' : 's' }}</x-slot:title>
        <x-slot:actions>
            <span class="nv-muted">
                {{ $grand['lines'] }} line{{ $grand['lines'] === 1 ? '' : 's' }} ·
                ₹ {{ number_format($grand['total'], 2) }}
            </span>
        </x-slot:actions>

        @if ($rows->isEmpty())
            <div class="nv-empty">
                <span class="nv-empty-icon"><x-icon name="inbox" /></span>
                <strong>Nothing charged in this range</strong>
                <p>Widen the dates, or clear the room filter.</p>
            </div>
        @else
            <div class="nv-table-wrap">
                <table class="nv-table">
                    <thead>
                        <tr>
                            <th>Room</th>
                            @foreach ($columns as $heading => $ignored)
                                <th>{{ $heading }}</th>
                            @endforeach
                            <th class="is-num">Qty</th>
                            <th class="is-num">Rate</th>
                            <th class="is-num">Tax</th>
                            <th class="is-num">Amount</th>
                        </tr>
                    </thead>

                    @foreach ($rows as $group)
                        <tbody>
                            {{--
                                The room's own subtotal sits at the TOP of its
                                block, not the bottom. A manager scanning this
                                wants the answer before the detail, and the
                                detail is what they drop into only when the
                                number surprises them.
                            --}}
                            <tr class="nv-rw-head">
                                <th>{{ $group->room_no }}</th>
                                <th colspan="{{ count($columns) }}">
                                    {{ $group->count }} line{{ $group->count === 1 ? '' : 's' }}
                                </th>
                                <th class="is-num">{{ rtrim(rtrim(number_format($group->qty, 2, '.', ''), '0'), '.') }}</th>
                                <th class="is-num"></th>
                                <th class="is-num">₹ {{ number_format($group->tax, 2) }}</th>
                                <th class="is-num">₹ {{ number_format($group->total, 2) }}</th>
                            </tr>

                            @foreach ($group->lines as $line)
                                <tr>
                                    <td class="nv-rw-cell"></td>
                                    @foreach ($columns as $cell)
                                        <td>{!! $cell($line) !!}</td>
                                    @endforeach
                                    <td class="is-num">{{ rtrim(rtrim(number_format((float) $line->qty, 2, '.', ''), '0'), '.') }}</td>
                                    <td class="is-num">₹ {{ number_format((float) $line->price, 2) }}</td>
                                    <td class="is-num">₹ {{ number_format((float) $line->tax_amount, 2) }}</td>
                                    <td class="is-num">₹ {{ number_format((float) $line->total_amount, 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    @endforeach

                    <tfoot>
                        <tr>
                            <th colspan="{{ count($columns) + 3 }}">Every room</th>
                            <th class="is-num">₹ {{ number_format($grand['tax'], 2) }}</th>
                            <th class="is-num">₹ {{ number_format($grand['total'], 2) }}</th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        @endif
    </x-card>
</div>
