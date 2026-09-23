@extends('layouts.app')

@section('title', 'Add Received')

@php $qty = fn ($n) => rtrim(rtrim(number_format((float) $n, 2), '0'), '.'); @endphp

@section('content')
    <x-page-header
        title="Received House Keeping"
        :subtitle="$receiptNo . ' · linen coming back from the laundry'"
        :crumbs="['Home' => url('/'), 'House Keeping', 'Received' => route('house-keeping.received'), 'Add Received']"
    >
        <x-slot:actions>
            <a href="{{ route('house-keeping.received') }}" class="nv-btn nv-btn-outline">Cancel</a>

            <button type="submit" form="receive-form" class="nv-btn nv-btn-primary">
                <x-icon name="check" /> Save Received
            </button>
        </x-slot:actions>
    </x-page-header>

    @if (session('error'))
        <div class="nv-mt"><x-alert tone="danger" title="Not saved">{{ session('error') }}</x-alert></div>
    @endif

    @if ($errors->any())
        <div class="nv-mt">
            <x-alert tone="danger" title="Please fix {{ $errors->count() }} thing(s)">{{ $errors->first() }}</x-alert>
        </div>
    @endif

    <form method="POST" action="{{ route('house-keeping.received.store') }}" id="receive-form" data-receive>
        @csrf

        <div class="nv-mt">
            <x-card>
                <div class="nv-form-grid nv-grid-4">
                    <x-field label="Receipt No.">
                        <x-input :value="$receiptNo" readonly />
                    </x-field>

                    <x-field label="Vendor" name="vendor_id" required
                             help="Pick the laundry and the list below fills with what they are holding.">
                        <select name="vendor_id" class="nv-select" data-receive-vendor>
                            <option value="">Select Vendor</option>
                            @foreach ($vendors as $vendor)
                                <option value="{{ $vendor->id }}"
                                        @selected((string) old('vendor_id', $vendorId) === (string) $vendor->id)>
                                    {{ $vendor->name }}
                                </option>
                            @endforeach
                        </select>
                    </x-field>

                    <x-field label="Receive Date" name="receive_date" required>
                        <x-input name="receive_date" type="date" :value="old('receive_date', $today)" />
                    </x-field>

                    <x-field label="Remark" name="remark">
                        <x-input name="remark" :value="old('remark')" placeholder="Optional" />
                    </x-field>
                </div>
            </x-card>
        </div>

        <div class="nv-mt">
            <x-card flush>
                <div class="nv-table-wrap">
                    <table class="nv-table nv-table-compact nv-issue-grid">
                        <thead>
                            <tr>
                                <th style="min-width:200px">Item Name</th>
                                <th class="is-num" title="Still with this vendor">Pending</th>
                                <th class="is-num">Received</th>
                                <th class="is-num" title="Came back torn or stained beyond use">Damaged</th>
                                <th class="is-num" title="Never came back">Missing</th>
                                <th class="is-num">Left with vendor</th>
                                <th style="min-width:160px">Remark</th>
                            </tr>
                        </thead>

                        <tbody data-receive-rows>
                            @forelse ($lines as $i => $line)
                                @php
                                    // What a failed save is bringing back for this
                                    // item, if anything — matched by item, not by
                                    // row number, because the outstanding list can
                                    // have shifted underneath it.
                                    $was = collect(old('lines', []))
                                        ->firstWhere('hk_item_id', (string) $line['hk_item_id'])
                                        ?? collect(old('lines', []))->firstWhere('hk_item_id', $line['hk_item_id'])
                                        ?? [];

                                    $counted = max(0, (float) ($was['received_qty'] ?? 0))
                                        + max(0, (float) ($was['damaged_qty'] ?? 0))
                                        + max(0, (float) ($was['missing_qty'] ?? 0));
                                @endphp

                                <tr class="nv-issue-row">
                                    <td>
                                        <strong>{{ $line['name'] }}</strong>
                                        <span class="nv-sub">{{ $line['unit'] }}</span>
                                        <input type="hidden" name="lines[{{ $i }}][hk_item_id]"
                                               value="{{ $line['hk_item_id'] }}" data-cell="hk_item_id" />
                                    </td>

                                    {{-- Raw numbers in the value, never number_format:
                                         a thousands separator makes parseFloat("1,200")
                                         come back as 1 and the line reads as over. --}}
                                    <td class="is-num">
                                        <input type="text" class="nv-input is-readonly" data-cell="pending"
                                               value="{{ (float) $line['pending'] }}" readonly tabindex="-1" />
                                    </td>

                                    @foreach (['received_qty', 'damaged_qty', 'missing_qty'] as $name)
                                        <td class="is-num">
                                            <input type="number" class="nv-input" data-cell="{{ $name }}"
                                                   name="lines[{{ $i }}][{{ $name }}]"
                                                   value="{{ (float) ($was[$name] ?? 0) }}" min="0"
                                                   max="{{ (float) $line['pending'] }}" step="1" />
                                        </td>
                                    @endforeach

                                    <td class="is-num">
                                        <input type="text" class="nv-input is-readonly" data-cell="left"
                                               value="{{ (float) max(0, $line['pending'] - $counted) }}"
                                               readonly tabindex="-1" />
                                    </td>

                                    <td>
                                        <input type="text" class="nv-input" name="lines[{{ $i }}][remark]"
                                               value="{{ $was['remark'] ?? '' }}" placeholder="Optional" />
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="nv-muted" style="padding:22px;text-align:center">
                                        Pick a vendor above to see what they are holding.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>

                        <tfoot>
                            <tr>
                                <th>Total</th>
                                <th class="is-num" data-rtotal="pending">0</th>
                                <th class="is-num" data-rtotal="received_qty">0</th>
                                <th class="is-num" data-rtotal="damaged_qty">0</th>
                                <th class="is-num" data-rtotal="missing_qty">0</th>
                                <th class="is-num" data-rtotal="left">0</th>
                                <th></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <x-slot:footer>
                    <p class="nv-help" style="margin:0">
                        <b>Damaged</b> and <b>Missing</b> come off the vendor's list too — the hotel is not
                        getting those pieces back either, and leaving them on would keep a finished job open
                        for ever. They are recorded separately so the loss stays visible.
                        Nothing here can add up to more than <b>Pending</b>.
                    </p>
                </x-slot:footer>
            </x-card>
        </div>
    </form>
@endsection

@push('scripts')
<script>
    window.LAUNDRY_RECEIVE = {
        pendingUrl: @json(route('house-keeping.received.pending')),
    };
</script>
<script src="{{ asset('js/laundry-receive.js') }}?v={{ filemtime(public_path('js/laundry-receive.js')) }}" defer></script>
@endpush
