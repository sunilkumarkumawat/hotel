{{--
    Cancelling an order.

    The reason is required, not polite. An order cancelled after the kitchen
    has cooked it is written line by line into the audit log with whatever is
    typed here, and "cancelled" on its own tells a manager reading it back
    nothing at all.
--}}

@canDelete('point-of-sale/pos')
    <div class="nv-modal-backdrop" id="cancel" data-modal="cancel">
        <div class="nv-modal" role="dialog" aria-modal="true" aria-labelledby="cancel-title">
            <div class="nv-modal-head">
                <strong id="cancel-title">Cancel {{ $order->order_no }}?</strong>
                <a href="#" class="nv-icon-btn" data-modal-close aria-label="Close">
                    <x-icon name="x" />
                </a>
            </div>

            <form method="POST" action="{{ route('point-of-sale.pos.order.cancel', $order->id) }}">
                @csrf

                @if ($order->kot_count)
                    <x-alert tone="danger" title="{{ $order->kot_count }} KOT{{ $order->kot_count === 1 ? ' has' : 's have' }} already gone to the kitchen">
                        Every item that was cooked will be written to the audit log against this till,
                        with the reason below.
                    </x-alert>
                @endif

                <x-field label="Why" name="reason" required>
                    <x-input name="reason" placeholder="Guest left, duplicate order, wrong table…" required />
                </x-field>

                <div class="nv-actions" style="justify-content:flex-end;margin-top:16px">
                    <a href="#" class="nv-btn nv-btn-ghost" data-modal-close>Keep the order</a>
                    <button type="submit" class="nv-btn nv-btn-danger">
                        <x-icon name="x-circle" /> Cancel order
                    </button>
                </div>
            </form>
        </div>
    </div>
@endCanDelete
