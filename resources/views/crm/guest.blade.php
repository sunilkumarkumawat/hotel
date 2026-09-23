@extends('layouts.app')

@section('title', $guest->name)

@php
    $money = fn ($n) => '₹' . number_format((float) $n, 2);
    $mayEdit = can_here('edit');

    $pinned = $notes->where('pinned', true);
    $rest = $notes->where('pinned', false);
@endphp

@section('content')
    <x-page-header
        :title="$guest->name"
        :subtitle="$guest->stays > 0
            ? $guest->stays . ' ' . \Illuminate\Support\Str::plural('stay', $guest->stays)
                . ' · ' . $guest->nights . ' ' . \Illuminate\Support\Str::plural('night', $guest->nights)
                . ' · first came ' . ($guest->first_stay_at?->format('M Y') ?? '—')
            : 'No stays yet — booked, but never arrived.'"
        :crumbs="['Home' => url('/'), 'Guest CRM', 'Guests' => route('crm.guests'), $guest->name]"
    >
        <x-slot:actions>
            <a href="{{ route('crm.guests') }}" class="nv-btn nv-btn-outline">
                <x-icon name="chevron-left" /> All guests
            </a>
        </x-slot:actions>
    </x-page-header>

    {{-- ── Blacklisted: said first, before anything else on the page ─────── --}}
    @if ($guest->is_blacklisted)
        <div class="nv-mt">
            <x-alert tone="danger" title="This guest is blacklisted">
                {{ $guest->blacklist_reason ?: 'No reason was recorded.' }}
                @if ($guest->blacklisted_on)
                    Marked {{ $guest->blacklisted_on->format('d M Y') }}.
                @endif
            </x-alert>
        </div>
    @endif

    {{-- ── What the desk should act on, above everything else ────────────── --}}
    @if ($pinned->isNotEmpty())
        <div class="nv-mt">
            <x-card title="Before they arrive" subtitle="Pinned notes — what whoever checks them in needs to know.">
                <div class="nv-crm-pins">
                    @foreach ($pinned as $note)
                        <div class="nv-crm-pin is-{{ $note->tone }}">
                            <span class="nv-crm-pin-kind">{{ $note->kind_label }}</span>
                            <p>{{ $note->body }}</p>
                            <small>
                                {{ $note->author?->name ?? 'the desk' }} ·
                                {{ $note->created_at?->format('d M Y') }}
                            </small>

                            @if ($mayEdit)
                                <form method="POST" action="{{ route('crm.guests.note.pin', $note) }}">
                                    @csrf
                                    <button type="submit" class="nv-btn nv-btn-sm nv-btn-ghost" title="Unpin">
                                        <x-icon name="x" />
                                    </button>
                                </form>
                            @endif
                        </div>
                    @endforeach
                </div>
            </x-card>
        </div>
    @endif

    <div class="nv-grid nv-grid-4 nv-mt">
        <x-stat label="Stays" :value="number_format((int) $guest->stays)" icon="desktop"
                :caption="$guest->last_stay_at ? 'Last ' . $guest->last_stay_at->format('d M Y') : 'Never arrived'" />
        <x-stat label="Lifetime value" :value="$money($guest->total_spend)" icon="wallet" tone="success"
                :caption="$guest->stays ? $money($guest->total_spend / max(1, $guest->stays)) . ' a stay' : null" />
        <x-stat label="Tier" :value="$guest->tier_label" icon="star" tone="info"
                :caption="$progress['label']
                    ? ($progress['stays_to_go'] > 0
                        ? $progress['stays_to_go'] . ' more ' . \Illuminate\Support\Str::plural('stay', $progress['stays_to_go']) . ' to ' . $progress['label']
                        : 'Almost at ' . $progress['label'])
                    : 'The top tier'" />
        <x-stat label="Points" :value="number_format((int) $guest->loyalty_points)" icon="sparkles" tone="warning"
                caption="Earned on room revenue" />
    </div>

    @if ($guest->totalsAreStale())
        <div class="nv-mt">
            <x-alert tone="warning" title="These figures are behind">
                They were last worked out {{ $guest->totals_at?->diffForHumans() ?? 'never' }}, and this guest has
                stayed since. Reloading this page recounts them.
            </x-alert>
        </div>
    @endif

    <div class="nv-grid nv-grid-main nv-mt">
        {{-- ── Left: the stays, and the feedback ─────────────────────────── --}}
        <div class="nv-stack">
            <x-card title="Stays" :subtitle="$stays->count() . ' on file, newest first'" :flush="true">
                @if ($stays->isEmpty())
                    <div class="nv-empty">
                        <span class="nv-empty-icon"><x-icon name="calendar" /></span>
                        <strong>No stays yet</strong>
                        <p>This guest exists because of a booking, but nobody has checked in against it.</p>
                    </div>
                @else
                    <div class="nv-table-wrap">
                        <table class="nv-table nv-table-compact">
                            <thead>
                                <tr>
                                    <th>Arrived</th>
                                    <th>Left</th>
                                    <th>Room</th>
                                    <th>Folio</th>
                                    <th>Bill</th>
                                    <th class="is-end">Paid</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($stays as $stay)
                                    <tr>
                                        <td>{{ \Carbon\CarbonImmutable::parse($stay->checkin_date)->format('d M Y') }}</td>
                                        <td>
                                            {{ \Carbon\CarbonImmutable::parse($stay->actual_checkout_date ?: $stay->expected_checkout_date)->format('d M Y') }}
                                            @if ($stay->status === 'in_house')
                                                <span class="nv-sub">in house now</span>
                                            @endif
                                        </td>
                                        <td>{{ $stay->room_no ?: '—' }}</td>
                                        <td>{{ $stay->folio_no }}</td>
                                        <td>{{ $stay->bill_no ?: '—' }}</td>
                                        <td class="is-end">
                                            {{ $stay->net_amount ? $money($stay->net_amount) : '—' }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </x-card>

            @if ($feedback->isNotEmpty())
                <x-card title="What they said" subtitle="Feedback after their stays.">
                    <div class="nv-crm-feedback">
                        @foreach ($feedback as $row)
                            <div class="nv-crm-fb">
                                <div class="nv-crm-fb-top">
                                    <span class="nv-fb-shown is-inline">
                                        @for ($i = 1; $i <= 5; $i++)
                                            <i @class(['is-on' => $row->overall && $i <= $row->overall])>★</i>
                                        @endfor
                                    </span>
                                    <small>{{ $row->answered_at?->format('d M Y') }}</small>
                                </div>

                                @if ($row->liked)
                                    <p><strong>Liked</strong> {{ $row->liked }}</p>
                                @endif

                                @if ($row->improve)
                                    <p class="nv-crm-improve"><strong>Could be better</strong> {{ $row->improve }}</p>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </x-card>
            @endif

            {{-- ── Every note, oldest business last ───────────────────────── --}}
            <x-card title="Notes" subtitle="What the desk has learned. Pin the ones that should meet them at arrival.">
                @canAdd('crm/guests')
                    <form method="POST" action="{{ route('crm.guests.note', $guest) }}" class="nv-crm-note-form">
                        @csrf

                        <div class="nv-crm-note-top">
                            <select name="kind" class="nv-select">
                                @foreach ($kinds as $key => $label)
                                    <option value="{{ $key }}" @selected($key === 'preference')>{{ $label }}</option>
                                @endforeach
                            </select>

                            <label class="nv-check-inline">
                                <input type="checkbox" name="pinned" value="1" />
                                Pin it
                            </label>
                        </div>

                        <x-textarea name="body" rows="2" placeholder="Asks for a high floor, away from the lift…" />

                        <button type="submit" class="nv-btn nv-btn-primary nv-btn-sm">
                            <x-icon name="plus" /> Add note
                        </button>
                    </form>

                    <hr class="nv-hr" />
                @endCanAdd

                @if ($rest->isEmpty() && $pinned->isEmpty())
                    <p class="nv-muted">Nothing written down yet.</p>
                @else
                    <div class="nv-crm-notes">
                        @foreach ($notes as $note)
                            <div class="nv-crm-note">
                                <span class="nv-crm-note-kind is-{{ $note->tone }}">{{ $note->kind_label }}</span>

                                <div>
                                    <p>{{ $note->body }}</p>
                                    <small>
                                        {{ $note->author?->name ?? 'the desk' }} ·
                                        {{ $note->created_at?->format('d M Y') }}
                                        @if ($note->pinned) · pinned @endif
                                    </small>
                                </div>

                                @if ($mayEdit)
                                    <div class="nv-row-actions">
                                        <form method="POST" action="{{ route('crm.guests.note.pin', $note) }}">
                                            @csrf
                                            <button type="submit" class="nv-btn nv-btn-sm nv-btn-ghost"
                                                    title="{{ $note->pinned ? 'Unpin' : 'Pin' }}">
                                                <x-icon name="star" />
                                            </button>
                                        </form>

                                        @canDelete('crm/guests')
                                            <form method="POST" action="{{ route('crm.guests.note.destroy', $note) }}"
                                                  data-confirm="Delete this note?" data-confirm-title="Delete note">
                                                @csrf @method('DELETE')
                                                <button type="submit" class="nv-btn nv-btn-sm nv-btn-ghost">
                                                    <x-icon name="trash" />
                                                </button>
                                            </form>
                                        @endCanDelete
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif
            </x-card>
        </div>

        {{-- ── Right: who they are, and the levers ───────────────────────── --}}
        <div class="nv-stack">
            <x-card title="Preferences and dates">
                @if ($mayEdit)
                    <form method="POST" action="{{ route('crm.guests.preferences', $guest) }}">
                        @csrf

                        <x-field label="What they like" name="preferences"
                                 help="One line each. This is what the desk reads before they arrive.">
                            <x-textarea name="preferences" :value="$guest->preferences" rows="4"
                                        placeholder="High floor · Extra pillows · No fish" />
                        </x-field>

                        <div class="nv-form-grid">
                            <x-field label="Birthday" name="dob">
                                <x-input type="date" name="dob" :value="$guest->dob?->toDateString()" />
                            </x-field>

                            <x-field label="Anniversary" name="anniversary">
                                <x-input type="date" name="anniversary" :value="$guest->anniversary?->toDateString()" />
                            </x-field>
                        </div>

                        <button type="submit" class="nv-btn nv-btn-primary nv-btn-sm nv-mt">
                            <x-icon name="check" /> Save
                        </button>
                    </form>
                @else
                    <p>{{ $guest->preferences ?: 'Nothing recorded.' }}</p>
                @endif
            </x-card>

            <x-card title="Contact">
                <div class="nv-crm-facts">
                    <div><span>Mobile</span><b>{{ $guest->mobile ?: '—' }}</b></div>
                    <div><span>Email</span><b>{{ $guest->email ?: '—' }}</b></div>
                    <div><span>Company</span><b>{{ $guest->company?->name ?: '—' }}</b></div>
                    <div><span>City</span><b>{{ $guest->city ?: '—' }}</b></div>
                    <div><span>First stay</span><b>{{ $guest->first_stay_at?->format('d M Y') ?: '—' }}</b></div>
                    <div><span>Nights</span><b>{{ number_format((int) $guest->nights) }}</b></div>
                </div>
            </x-card>

            {{-- ── Points, as a ledger ───────────────────────────────────── --}}
            <x-card title="Loyalty" :subtitle="number_format((int) $guest->loyalty_points) . ' points'">
                @canEdit('crm/guests')
                    <form method="POST" action="{{ route('crm.guests.points', $guest) }}" class="nv-crm-points">
                        @csrf
                        <input type="number" name="points" class="nv-input" placeholder="±" required />
                        <input type="text" name="reason" class="nv-input" placeholder="Why" maxlength="255" required />
                        <button type="submit" class="nv-btn nv-btn-sm nv-btn-outline">Adjust</button>
                    </form>

                    <hr class="nv-hr" />
                @endCanEdit

                @if ($ledger->isEmpty())
                    <p class="nv-muted">No points yet. They are earned on room revenue when a stay is billed.</p>
                @else
                    <div class="nv-crm-ledger">
                        @foreach ($ledger as $line)
                            <div>
                                <span>
                                    {{ $line->reason }}
                                    <small>{{ \Carbon\CarbonImmutable::parse($line->entry_date)->format('d M Y') }}</small>
                                </span>
                                <b @class(['is-minus' => $line->points < 0])>
                                    {{ $line->points > 0 ? '+' : '' }}{{ number_format($line->points) }}
                                </b>
                            </div>
                        @endforeach
                    </div>
                @endif
            </x-card>

            {{-- ── The blacklist, kept at the bottom out of the way ───────── --}}
            @canDelete('crm/guests')
                <x-card title="Blacklist">
                    <form method="POST" action="{{ route('crm.guests.blacklist', $guest) }}"
                          data-confirm="{{ $guest->is_blacklisted
                              ? 'Take ' . $guest->name . ' off the blacklist?'
                              : 'Blacklist ' . $guest->name . '? The booking screen will warn about them.' }}"
                          data-confirm-title="{{ $guest->is_blacklisted ? 'Lift the blacklist' : 'Blacklist guest' }}">
                        @csrf

                        @unless ($guest->is_blacklisted)
                            <x-field label="Reason" name="blacklist_reason"
                                     help="Required. A blacklist with no reason is one nobody can undo fairly.">
                                <x-input name="blacklist_reason" maxlength="255" />
                            </x-field>
                        @endunless

                        <button type="submit"
                                @class(['nv-btn', 'nv-btn-sm', 'nv-btn-block',
                                        'nv-btn-outline' => $guest->is_blacklisted,
                                        'nv-btn-danger' => ! $guest->is_blacklisted])>
                            <x-icon name="shield" />
                            {{ $guest->is_blacklisted ? 'Take off the blacklist' : 'Blacklist this guest' }}
                        </button>
                    </form>
                </x-card>
            @endCanDelete
        </div>
    </div>
@endsection
