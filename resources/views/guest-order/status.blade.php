@extends('layouts.public')

@section('title', 'Your order — Table ' . $table->name)

@section('content')
<div class="nv-om">
    <div class="nv-om-card">
        <div class="nv-fb-head">
            <p class="nv-fb-hotel">{{ $hotel?->legal_name ?: ($hotel?->branch_name ?: config('app.name')) }}</p>
            <h1>Table {{ $table->name }}</h1>
            <p>Sent {{ $request->created_at->format('h:i A') }}</p>
        </div>

        <div class="nv-fb-body">
            <div class="nv-os-banner" id="os-banner" data-status="{{ $state['status'] }}">
                <span class="nv-os-dot"></span>
                <p id="os-label">{{ $state['label'] }}</p>
            </div>

            <ul class="nv-os-items" id="os-items">
                @foreach ($state['items'] as $i)
                    <li data-item="{{ $loop->index }}">
                        <span class="nv-os-item-name">{{ rtrim(rtrim(number_format((float) $i['qty'], 2), '0'), '.') }} × {{ $i['name'] }}</span>
                        <span class="nv-badge nv-os-kstatus" data-kstatus>
                            {{ $i['kitchen_status'] ? \App\Models\Pos\PosOrderItem::KITCHEN_STATUSES[$i['kitchen_status']] ?? '' : '—' }}
                        </span>
                    </li>
                @endforeach
            </ul>

            @if ($request->guest_note)
                <p class="nv-fb-fine">Note you left: “{{ $request->guest_note }}”</p>
            @endif

            <a href="{{ route('guest-order.menu', ['table' => $table->id, 'code' => $code]) }}"
               class="nv-om-more">
                <x-icon name="plus" /> Order something else
            </a>
        </div>
    </div>

    <p class="nv-fb-foot">
        {{ $hotel?->branch_name }}{{ $hotel?->mobile_number ? ' · ' . $hotel->mobile_number : '' }}
    </p>
</div>
@endsection

@push('scripts')
    <script>
        window.guestOrderPoll = @json($request->status === 'pending' || $request->status === 'approved'
            ? route('guest-order.poll', ['table' => $table->id, 'code' => $code, 'token' => $request->token])
            : null);
        // Read off the model rather than typed a second time in JavaScript,
        // so the wording on a live-updated badge can never drift from what
        // the page showed when it first loaded.
        window.kitchenStatusLabels = @json(\App\Models\Pos\PosOrderItem::KITCHEN_STATUSES);
    </script>
    <script src="{{ asset('js/guest-order.js') }}?v={{ file_exists(public_path('js/guest-order.js')) ? filemtime(public_path('js/guest-order.js')) : time() }}" defer></script>
@endpush
