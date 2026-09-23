@extends('layouts.app')

@section('title', 'Ledgers')

@section('content')
    <x-page-header
        title="Ledgers"
        subtitle="Every named account money can move to or from."
        :crumbs="['Home' => url('/'), 'Accounting' => route('accounting.day-book'), 'Ledger']"
    />

    <div class="nv-grid nv-grid-2 nv-mt">
        @canAdd('accounting/ledger')
            <x-card :title="$editing ? 'Edit ' . $editing->name : 'Add a ledger'">
                <form method="POST" action="{{ route('accounting.ledger.save') }}">
                    @csrf

                    @if ($editing)
                        <input type="hidden" name="id" value="{{ $editing->id }}" />
                    @endif

                    <div class="nv-grid nv-grid-2">
                        <x-field label="Name" name="name" required>
                            <x-input name="name" :value="$editing?->name" />
                        </x-field>

                        <x-field label="Code" name="code">
                            <x-input name="code" :value="$editing?->code" />
                        </x-field>

                        <x-field label="Group" name="account_group_id" required wide>
                            <select name="account_group_id" id="account_group_id" class="nv-select">
                                @foreach ($groups as $group)
                                    <option value="{{ $group->id }}" @selected($editing?->account_group_id == $group->id)>
                                        {{ $group->path }} ({{ $group->nature_label }})
                                    </option>
                                @endforeach
                            </select>
                        </x-field>

                        <x-field label="Opening balance" name="opening_balance">
                            <x-input name="opening_balance" type="number" step="0.01" min="0"
                                     :value="(float) ($editing?->opening_balance ?? 0)" />
                        </x-field>

                        <x-field label="Dr or Cr" name="balance_type" required>
                            <x-select name="balance_type" :options="$balanceTypes" :selected="$editing?->balance_type ?? 'dr'" />
                        </x-field>

                        <x-field label="Is this the cash box or a bank account?" name="cash_type" required wide
                                 help="This is what makes the Cash Book, the Bank Book and the Contra voucher possible.">
                            <x-select name="cash_type" :options="$cashTypes" :selected="$editing?->cash_type ?? 'none'" />
                        </x-field>

                        <x-field label="GST number" name="gst_no">
                            <x-input name="gst_no" :value="$editing?->gst_no" />
                        </x-field>

                        <x-field label="Mobile" name="mobile">
                            <x-input name="mobile" :value="$editing?->mobile" />
                        </x-field>

                        <x-field label="Bank account no." name="bank_account_no">
                            <x-input name="bank_account_no" :value="$editing?->bank_account_no" />
                        </x-field>

                        <x-field label="IFSC" name="bank_ifsc">
                            <x-input name="bank_ifsc" :value="$editing?->bank_ifsc" />
                        </x-field>

                        <x-field label="Email" name="email">
                            <x-input name="email" type="email" :value="$editing?->email" />
                        </x-field>

                        <x-field label="Address" name="address">
                            <x-input name="address" :value="$editing?->address" />
                        </x-field>

                        <x-field label="On" name="status" wide>
                            <label class="nv-check">
                                <input type="hidden" name="status" value="0" />
                                <input type="checkbox" name="status" value="1" @checked($editing ? $editing->isActive() : true) />
                                <span>Can be posted to</span>
                            </label>
                        </x-field>
                    </div>

                    <div class="nv-actions" style="justify-content:flex-end;margin-top:14px">
                        @if ($editing)
                            <a href="{{ route('accounting.ledger') }}" class="nv-btn nv-btn-ghost">Cancel</a>
                        @endif

                        <button type="submit" class="nv-btn nv-btn-primary">
                            <x-icon name="check" /> {{ $editing ? 'Save changes' : 'Add ledger' }}
                        </button>
                    </div>
                </form>
            </x-card>
        @endCanAdd

        <x-card flush>
            <x-slot:title>{{ $ledgers->count() }} ledger(s)</x-slot:title>

            <x-slot:actions>
                <form method="GET" class="nv-actions">
                    <select name="cash" class="nv-select nv-input-sm" onchange="this.form.submit()" aria-label="Kind">
                        <option value="">All kinds</option>
                        @foreach ($cashTypes as $key => $label)
                            <option value="{{ $key }}" @selected($filters['cash'] === $key)>{{ $label }}</option>
                        @endforeach
                    </select>

                    <div class="nv-field-search">
                        <x-icon name="search" />
                        <input type="search" name="q" value="{{ $filters['q'] }}" class="nv-input nv-input-sm"
                               placeholder="Name, code, mobile…" />
                    </div>
                </form>
            </x-slot:actions>

            @if ($ledgers->isEmpty())
                <div class="nv-empty">
                    <span class="nv-empty-icon"><x-icon name="file" /></span>
                    <strong>No ledgers yet</strong>
                    <p>Add the cash box first — nothing can be paid or received without it.</p>
                </div>
            @else
                <div class="nv-table-wrap">
                    <table class="nv-table">
                        <thead>
                            <tr>
                                <th>Ledger</th>
                                <th>Group</th>
                                <th class="is-num">Balance</th>
                                <th style="width:110px"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($ledgers as $ledger)
                                @php
                                    $balance = (float) ($balances[$ledger->id] ?? 0);
                                    $side = \App\Support\Ledgers::side($balance);
                                @endphp

                                <tr @class(['nv-fac-off' => ! $ledger->isActive()])>
                                    <td>
                                        <strong>{{ $ledger->name }}</strong>
                                        <span class="nv-sub">
                                            {{ $ledger->code ? $ledger->code . ' · ' : '' }}{{ $ledger->cash_type !== 'none' ? $cashTypes[$ledger->cash_type] : '' }}
                                        </span>
                                    </td>

                                    <td>{{ $ledger->group?->name ?? 'Ungrouped' }}</td>

                                    <td class="is-num">
                                        @if ($side)
                                            ₹ {{ number_format(abs($balance), 2) }}
                                            <span class="nv-sub">{{ strtoupper($side) }}</span>
                                        @else
                                            <span class="nv-muted">nil</span>
                                        @endif
                                    </td>

                                    <td class="nv-fac-row-actions">
                                        @canView('accounting/ledger-statement')
                                            <a href="{{ route('accounting.ledger-statement', ['ledger' => $ledger->id]) }}"
                                               class="nv-icon-btn" title="Statement"><x-icon name="eye" /></a>
                                        @endCanView

                                        @canEdit('accounting/ledger')
                                            <a href="{{ route('accounting.ledger', ['edit' => $ledger->id]) }}"
                                               class="nv-icon-btn" title="Edit"><x-icon name="pencil" /></a>
                                        @endCanEdit

                                        @canDelete('accounting/ledger')
                                            @unless ($ledger->isSystem())
                                                <form method="POST" action="{{ route('accounting.ledger.toggle', $ledger->id) }}">
                                                    @csrf
                                                    <button type="submit" class="nv-icon-btn"
                                                            title="{{ $ledger->isActive() ? 'Switch off' : 'Switch back on' }}">
                                                        <x-icon :name="$ledger->isActive() ? 'x-circle' : 'refresh'" />
                                                    </button>
                                                </form>
                                            @endunless
                                        @endCanDelete
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-card>
    </div>
@endsection
