{{--
    The strip across the top of every till screen.

    One dropdown holds both questions the cashier is answering — which outlet,
    and whether they are looking at its tables or at the rooms — because on a
    busy evening they are one decision, not two. An outlet that has not been
    switched on for room service simply has no Rooms line under it.

    Expects: $nav (from PosController::nav), $outlet, $outlets, and optionally
    $mode and $current.
--}}

@php
    $mode = $mode ?? 'restaurant';
    $current = $current ?? 'floor';
@endphp

<div class="nv-till-bar">
    <form method="GET" action="{{ route('point-of-sale.pos') }}" class="nv-till-where">
        <label for="till-view" class="nv-till-where-label">Billing at</label>

        <select name="view" id="till-view" class="nv-select" data-autosubmit>
            @forelse ($outlets as $row)
                <optgroup label="{{ $row->name }}">
                    <option value="{{ $row->id }}|restaurant"
                            @selected($outlet && $outlet->id === $row->id && $mode !== 'room')>
                        {{ $row->name }} — Restaurant
                    </option>

                    @if ($row->pos_room_service)
                        <option value="{{ $row->id }}|room"
                                @selected($outlet && $outlet->id === $row->id && $mode === 'room')>
                            {{ $row->name }} — Room
                        </option>
                    @endif
                </optgroup>
            @empty
                <option value="">No outlet</option>
            @endforelse
        </select>

        {{-- Works with scripts blocked; the script above just saves the click. --}}
        <noscript>
            <button type="submit" class="nv-btn nv-btn-outline nv-btn-sm">Go</button>
        </noscript>
    </form>

    <nav class="nv-till-nav" aria-label="Point of sale">
        @foreach ($nav as $link)
            <a href="{{ $link['url'] }}"
               @class(['nv-till-link', 'is-active' => $current === $link['key']])
               @if ($current === $link['key']) aria-current="page" @endif>
                <x-icon :name="$link['icon']" />
                <span>{{ $link['label'] }}</span>
            </a>
        @endforeach

        @canView('point-of-sale/pos')
            @php
                // Read here rather than threaded through every screen that
                // includes this bar — one small indexed count, so a table's
                // request shows up the moment anyone loads any till screen.
                $pendingGuestCount = \App\Models\Pos\PosGuestRequest::query()
                    ->where('branch_id', \App\Helpers\Helper::getActiveBranchId())
                    ->pending()
                    ->count();
            @endphp
            <a href="{{ route('point-of-sale.pos.guest-requests') }}"
               @class(['nv-till-link', 'is-active' => $current === 'guest-requests'])>
                <x-icon name="bell" />
                <span>Guest Requests</span>
                @if ($pendingGuestCount)
                    <span class="nv-till-badge">{{ $pendingGuestCount }}</span>
                @endif
            </a>
        @endCanView

        @canView('point-of-sale/kitchen-display')
            <a href="{{ route('point-of-sale.kitchen-display', $outlet ? ['outlet' => $outlet->id] : []) }}"
               class="nv-till-link is-kds">
                <x-icon name="activity" />
                <span>Kitchen</span>
            </a>
        @endCanView
    </nav>
</div>
