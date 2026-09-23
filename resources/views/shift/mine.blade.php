@extends('layouts.app')

@section('title', 'My Shift')

@php
    $money = fn ($n) => '₹' . number_format((float) $n, 2);

    $cashModes = collect($modes)->filter(fn ($m) => $m['type'] === 'cash');
@endphp

@section('content')
    <x-page-header
        title="My Shift"
        subtitle="What you have taken, and what should be in the drawer."
        :crumbs="['Home' => url('/'), 'Shift', 'My Shift']"
    >
        <x-slot:actions>
            @canView('shift/reports')
                <a href="{{ route('shift.reports') }}" class="nv-btn nv-btn-outline">
                    <x-icon name="chart" /> All shifts
                </a>
            @endCanView
        </x-slot:actions>
    </x-page-header>

    @if (! $shift)
        {{-- ── Nothing open: start one ────────────────────────────────────── --}}
        <div class="nv-grid nv-grid-2 nv-mt">
            <x-card title="Open a shift" subtitle="Count what is in the drawer before you take a rupee.">
                <form method="POST" action="{{ route('shift.my-shift.open') }}" class="nv-stack">
                    @csrf

                    <x-field label="Opening float" name="opening_float"
                             help="The cash already in the drawer. Zero is a fine answer — it just has to be the true one.">
                        <x-input type="number" step="0.01" min="0" name="opening_float" value="0" />
                    </x-field>

                    <x-field label="Shift" name="name" help="Left blank, it is named after the time of day.">
                        <x-input name="name" :value="\App\Support\Shifts::partOfDay()" maxlength="40" />
                    </x-field>

                    <x-field label="Note" name="remark" wide>
                        <x-textarea name="remark" rows="2" placeholder="Anything worth saying about how you found the drawer." />
                    </x-field>

                    <div class="nv-actions">
                        <button type="submit" class="nv-btn nv-btn-primary" @disabled(! can_do('shift/my-shift', 'add'))>
                            <x-icon name="check-circle" /> Open shift
                        </button>
                    </div>
                </form>
            </x-card>

            <x-card title="How this works">
                <div class="nv-sh-how">
                    <p>
                        While your shift is open, every payment you take — room bills, advance deposits,
                        restaurant bills, cash in and cash out — is counted against it. Nothing is copied
                        anywhere: the shift simply asks the payment rows who took them and when.
                    </p>
                    <p>
                        At the end you count the drawer and type what you found. The screen shows what the
                        books expected <strong>after</strong> you have typed it, not before, because a
                        count you can read off the screen first is not a count.
                    </p>
                    <p class="nv-muted">
                        Money taken while no shift is open still goes on the guest's bill and into the day
                        book. It shows up on the manager's screen as unattached, which is the point of it.
                    </p>
                </div>
            </x-card>
        </div>
    @else
        {{-- ── The open drawer ────────────────────────────────────────────── --}}
        <div class="nv-mt">
            <div class="nv-sh-open">
                <div>
                    <x-badge tone="info">{{ $shift->shift_no }}</x-badge>
                    <h2>{{ $shift->name ?: 'Shift' }} · open {{ $shift->length }}</h2>
                    <p class="nv-muted">
                        Opened {{ $shift->opened_at->format('d M Y, h:i A') }} with
                        {{ $money($shift->opening_float) }} float.
                        {{ $figures['totals']['count'] }}
                        {{ \Illuminate\Support\Str::plural('transaction', $figures['totals']['count']) }} so far.
                    </p>
                </div>

                <div class="nv-sh-open-figures">
                    <div>
                        <span>Taken in</span>
                        <b class="nv-sh-in">{{ $money($figures['totals']['in']) }}</b>
                    </div>
                    <div>
                        <span>Paid out</span>
                        <b class="nv-sh-out">{{ $money($figures['totals']['out']) }}</b>
                    </div>
                </div>
            </div>
        </div>

        <div class="nv-grid nv-grid-main nv-mt">
            <div class="nv-stack">
                {{-- Every payment, in the order it happened. --}}
                <x-card title="What you have taken" :subtitle="$figures['totals']['count'] . ' since you opened'" :flush="true">
                    @if ($lines === [])
                        <div class="nv-empty">
                            <span class="nv-empty-icon"><x-icon name="wallet" /></span>
                            <strong>Nothing yet</strong>
                            <p>Payments you take will appear here as you take them.</p>
                        </div>
                    @else
                        <div class="nv-table-wrap">
                            <table class="nv-table nv-table-compact">
                                <thead>
                                    <tr>
                                        <th>Time</th>
                                        <th>Source</th>
                                        <th>Particulars</th>
                                        <th>Mode</th>
                                        <th class="is-end">Amount</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($lines as $line)
                                        <tr>
                                            <td>{{ \Carbon\CarbonImmutable::parse($line['at'])->format('h:i A') }}</td>
                                            <td>{{ $line['source'] }}</td>
                                            <td>
                                                {{ $line['particulars'] }}
                                                @if ($line['reference'])
                                                    <span class="nv-sub">Ref {{ $line['reference'] }}</span>
                                                @endif
                                            </td>
                                            <td>{{ $modes[$line['pay_mode_id']]['name'] ?? 'Not stated' }}</td>
                                            <td @class(['is-end', 'nv-sh-in' => $line['direction'] === 'in', 'nv-sh-out' => $line['direction'] === 'out'])>
                                                {{ $line['direction'] === 'out' ? '−' : '' }}{{ $money($line['amount']) }}
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </x-card>
            </div>

            <div class="nv-stack">
                {{-- ── The close. The count comes first, on purpose. ───────── --}}
                <x-card title="Close the shift" subtitle="Count the drawer, then type what you found.">
                    <form method="POST" action="{{ route('shift.my-shift.close') }}" class="nv-stack"
                          data-confirm="Close {{ $shift->shift_no }}? The figures are frozen at this point and the shift cannot be reopened."
                          data-confirm-title="Close this shift"
                          data-confirm-action="Close shift">
                        @csrf

                        <div class="nv-sh-count">
                            @foreach ($figures['modes'] as $mode)
                                <label class="nv-sh-count-row">
                                    <span>
                                        {{ $mode['name'] }}
                                        {{-- A mode called "Cash" does not need "Cash" written under it. --}}
                                        @if (strcasecmp($mode['name'], $mode['type']) !== 0)
                                            <small>{{ ucfirst($mode['type']) }}</small>
                                        @endif
                                    </span>
                                    <input type="number" step="0.01" min="0"
                                           name="counted[{{ $mode['id'] }}]"
                                           class="nv-input is-num" placeholder="0.00" />
                                </label>
                            @endforeach
                        </div>

                        <p class="nv-help">
                            A box left blank counts as zero. Cash is the one that decides whether the
                            shift is short; the card and UPI totals are recorded so a machine that
                            disagrees is caught tonight rather than at the month end.
                        </p>

                        <x-field label="Note" name="close_note" wide
                                 help="If it does not balance, this is where you say what you think happened.">
                            <x-textarea name="close_note" rows="2" />
                        </x-field>

                        <div class="nv-actions">
                            <button type="submit" class="nv-btn nv-btn-primary" @disabled(! can_do('shift/my-shift', 'add'))>
                                <x-icon name="lock" /> Count and close
                            </button>
                        </div>
                    </form>
                </x-card>

                <x-card title="Where it came from">
                    <div class="nv-crm-facts">
                        @foreach ([
                            'rooms' => 'Room bills',
                            'deposits' => 'Advance deposits',
                            'pos' => 'Restaurant',
                            'petty_in' => 'Cash received',
                            'refunds' => 'Deposit refunds',
                            'petty_out' => 'Cash paid out',
                        ] as $key => $label)
                            @if (($figures['sources'][$key] ?? 0) > 0)
                                <div>
                                    <span>{{ $label }}</span>
                                    <b @class(['nv-sh-out' => in_array($key, ['refunds', 'petty_out'], true)])>
                                        {{ in_array($key, ['refunds', 'petty_out'], true) ? '−' : '' }}{{ $money($figures['sources'][$key]) }}
                                    </b>
                                </div>
                            @endif
                        @endforeach
                    </div>

                    @if ($cashModes->isEmpty())
                        <x-alert tone="warning" title="No cash pay mode is set up">
                            Add one under Masters → Pay Mode with its type set to cash, or nothing on this
                            screen can tell the drawer from the card machine.
                        </x-alert>
                    @endif
                </x-card>
            </div>
        </div>

        @if ($recent->isNotEmpty())
            <div class="nv-mt">
                <x-card title="Your last few shifts" :flush="true">
                    <div class="nv-table-wrap">
                        <table class="nv-table nv-table-compact">
                            <thead>
                                <tr>
                                    <th>Shift</th>
                                    <th>Closed</th>
                                    <th class="is-end">Expected</th>
                                    <th class="is-end">Counted</th>
                                    <th class="is-end">Difference</th>
                                    <th class="is-end">&nbsp;</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($recent as $past)
                                    <tr>
                                        <td><strong>{{ $past->shift_no }}</strong>
                                            <span class="nv-sub">{{ $past->name }}</span></td>
                                        <td>{{ $past->closed_at?->format('d M Y, h:i A') }}</td>
                                        <td class="is-end">{{ $money($past->cash_expected) }}</td>
                                        <td class="is-end">{{ $money($past->cash_counted) }}</td>
                                        <td class="is-end">
                                            <x-badge :tone="$past->variance_tone">
                                                {{ abs((float) $past->variance) < 0.005 ? 'Balanced'
                                                    : ($past->isShort() ? 'Short ' : 'Over ') . $money(abs((float) $past->variance)) }}
                                            </x-badge>
                                        </td>
                                        <td class="is-end">
                                            @canView('shift/reports')
                                                <a href="{{ route('shift.reports.show', $past->id) }}"
                                                   class="nv-btn nv-btn-sm nv-btn-ghost">
                                                    <x-icon name="external" /> Open
                                                </a>
                                            @endCanView
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </x-card>
            </div>
        @endif
    @endif
@endsection
