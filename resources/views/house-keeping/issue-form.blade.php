@extends('layouts.app')

@section('title', 'Add Issue')

@section('content')
    <x-page-header
        title="Issue House Keeping"
        :subtitle="$issueNo . ' · linen going out to the laundry'"
        :crumbs="['Home' => url('/'), 'House Keeping', 'Issue' => route('house-keeping.issue'), 'Add Issue']"
    >
        <x-slot:actions>
            <a href="{{ route('house-keeping.issue') }}" class="nv-btn nv-btn-outline">Cancel</a>

            <button type="submit" form="issue-form" class="nv-btn nv-btn-primary">
                <x-icon name="check" /> Save Issue
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

    @if ($items->isEmpty() || $vendors->isEmpty())
        <div class="nv-mt">
            <x-alert tone="info" title="Set the two lists up first">
                @if ($vendors->isEmpty()) There is no vendor yet — press <b>+ New vendor</b>. @endif
                @if ($items->isEmpty()) There is no item yet — press <b>+ New item</b> and add a bedsheet or a towel. @endif
                Both take a few seconds and you only do it once.
            </x-alert>
        </div>
    @endif

    <form method="POST" action="{{ route('house-keeping.issue.store') }}" id="issue-form" data-issue>
        @csrf

        <div class="nv-mt">
            <x-card>
                <div class="nv-form-grid nv-grid-4">
                    <x-field label="Issue No.">
                        <x-input :value="$issueNo" readonly />
                    </x-field>

                    <x-field label="Vendor" name="vendor_id" required
                             help="Whose van is taking it — the Prev Qty column fills from this.">
                        <select name="vendor_id" class="nv-select" data-issue-vendor>
                            <option value="">Select Vendor</option>
                            @foreach ($vendors as $vendor)
                                <option value="{{ $vendor->id }}" @selected((string) old('vendor_id') === (string) $vendor->id)>
                                    {{ $vendor->name }}
                                </option>
                            @endforeach
                        </select>
                    </x-field>

                    <x-field label="Issue Date" name="issue_date" required>
                        <x-input name="issue_date" type="date" :value="old('issue_date', $today)" />
                    </x-field>

                    <x-field label="Remark" name="remark">
                        <x-input name="remark" :value="old('remark')" placeholder="Optional" />
                    </x-field>
                </div>

                <div class="nv-actions">
                    <button type="button" class="nv-btn nv-btn-outline nv-btn-sm" data-open="new-vendor">
                        <x-icon name="plus" /> New vendor
                    </button>
                    <button type="button" class="nv-btn nv-btn-outline nv-btn-sm" data-open="new-item">
                        <x-icon name="plus" /> New item
                    </button>
                </div>
            </x-card>
        </div>

        {{-- ── The grid ──────────────────────────────────────────────────── --}}
        <div class="nv-mt">
            <x-card flush>
                <div class="nv-table-wrap">
                    <table class="nv-table nv-table-compact nv-issue-grid">
                        <thead>
                            <tr>
                                <th style="min-width:200px">Item Name</th>
                                <th class="is-num" title="Already with this vendor, not yet returned">Prev Qty</th>
                                <th class="is-num">Std Qty</th>
                                <th class="is-num">Exp Qty</th>
                                <th class="is-num" title="Re-washed free — counted, not charged">ReWash</th>
                                <th class="is-num">Std Rate</th>
                                <th class="is-num">Exp Rate</th>
                                <th class="is-num">Amount</th>
                                <th style="width:88px">Actions</th>
                            </tr>
                        </thead>

                        {{-- Rendered here rather than built by the browser, so
                             the note can still be written if the script never
                             loads, and so a failed save keeps what was typed. --}}
                        <tbody data-issue-rows>
                            @foreach ($rows as $i => $line)
                                @include('house-keeping.partials.issue-row', [
                                    'i' => $i,
                                    'line' => $line,
                                    'items' => $items,
                                ])
                            @endforeach
                        </tbody>

                        <tfoot>
                            <tr>
                                <th colspan="2">Total</th>
                                <th class="is-num" data-total="std_qty">0</th>
                                <th class="is-num" data-total="exp_qty">0</th>
                                <th class="is-num" data-total="rewash_qty">0</th>
                                <th colspan="2"></th>
                                <th class="is-num" data-total="amount">₹0.00</th>
                                <th></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <x-slot:footer>
                    <p class="nv-help" style="margin:0">
                        <b>Prev Qty</b> is what this vendor is already holding of that item — it fills in
                        when you pick the vendor and is not typed. <b>ReWash</b> goes out free, so it is
                        counted but never charged. <b>Amount</b> = Std&nbsp;Qty × Std&nbsp;Rate +
                        Exp&nbsp;Qty × Exp&nbsp;Rate.
                    </p>
                </x-slot:footer>
            </x-card>
        </div>
    </form>

    {{-- The row the browser clones when "+" is pressed. Its fields carry no
         name until they are cloned, so nothing in here is ever submitted. --}}
    <template data-issue-template>
        @include('house-keeping.partials.issue-row', ['i' => null, 'line' => [], 'items' => $items])
    </template>

    {{-- ── Quick add: vendor ─────────────────────────────────────────────── --}}
    <div class="nv-modal-backdrop" data-modal="new-vendor">
        <div class="nv-modal" role="dialog" aria-modal="true" aria-labelledby="new-vendor-title">
            <div class="nv-modal-head">
                <strong id="new-vendor-title">New vendor</strong>
                <button type="button" class="nv-icon-btn" data-modal-close aria-label="Close"><x-icon name="x" /></button>
            </div>

            <form method="POST" action="{{ route('house-keeping.issue.vendor') }}">
                @csrf

                <div class="nv-form-grid nv-grid-2">
                    <x-field label="Name" name="name" required wide>
                        <x-input name="name" placeholder="Sharma Dry Cleaners" />
                    </x-field>

                    <x-field label="Mobile" name="mobile">
                        <x-input name="mobile" />
                    </x-field>

                    <x-field label="GST No." name="gst_no">
                        <x-input name="gst_no" />
                    </x-field>

                    <x-field label="Address" name="address" wide>
                        <x-input name="address" />
                    </x-field>
                </div>

                <div class="nv-actions" style="justify-content:flex-end;margin-top:16px">
                    <button type="button" class="nv-btn nv-btn-ghost" data-modal-close>Cancel</button>
                    <button type="submit" class="nv-btn nv-btn-primary">Save vendor</button>
                </div>
            </form>
        </div>
    </div>

    {{-- ── Quick add: item ───────────────────────────────────────────────── --}}
    <div class="nv-modal-backdrop" data-modal="new-item">
        <div class="nv-modal" role="dialog" aria-modal="true" aria-labelledby="new-item-title">
            <div class="nv-modal-head">
                <strong id="new-item-title">New item</strong>
                <button type="button" class="nv-icon-btn" data-modal-close aria-label="Close"><x-icon name="x" /></button>
            </div>

            <form method="POST" action="{{ route('house-keeping.issue.item') }}">
                @csrf

                <div class="nv-form-grid nv-grid-2">
                    <x-field label="Item name" name="name" required wide>
                        <x-input name="name" placeholder="Bedsheet, Towel, Pillow cover…" />
                    </x-field>

                    <x-field label="Unit" name="unit">
                        <x-select name="unit" :options="$units" selected="pcs" />
                    </x-field>

                    <x-field label="Std Rate" name="std_rate" help="Per piece, normal wash.">
                        <x-input name="std_rate" type="number" step="0.01" min="0" value="0" />
                    </x-field>

                    <x-field label="Exp Rate" name="exp_rate" help="Per piece, express wash." wide>
                        <x-input name="exp_rate" type="number" step="0.01" min="0" value="0" />
                    </x-field>
                </div>

                <div class="nv-actions" style="justify-content:flex-end;margin-top:16px">
                    <button type="button" class="nv-btn nv-btn-ghost" data-modal-close>Cancel</button>
                    <button type="submit" class="nv-btn nv-btn-primary">Save item</button>
                </div>
            </form>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    window.LAUNDRY = { pendingUrl: @json(route('house-keeping.issue.pending')) };
</script>
<script src="{{ asset('js/laundry.js') }}?v={{ filemtime(public_path('js/laundry.js')) }}" defer></script>
@endpush
