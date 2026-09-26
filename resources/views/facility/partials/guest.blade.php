{{--
    Who the booking is for.

    Two ways in, on purpose: pick an in-house stay and the three fields fill
    themselves in, or type a walk-in's details and leave the stay empty. A
    hotel sells the pool and the hall to people who are not staying, and a
    screen that insists on a room number cannot take their money.

    Expects: $stays (in-house CheckIns), $row (the booking being edited).
--}}

<x-field label="In-house guest" name="check_in_id" help="Leave empty for a walk-in.">
    <select name="check_in_id" id="check_in_id" class="nv-select" data-stay-pick>
        <option value="">Walk-in — not staying</option>
        @foreach ($stays as $stay)
            <option value="{{ $stay->id }}"
                    data-name="{{ $stay->guest_name }}"
                    data-mobile="{{ $stay->mobile }}"
                    data-room="{{ $stay->room?->room_no ?: $stay->folio_no }}"
                    @selected(old('check_in_id', $row->check_in_id) == $stay->id)>
                {{ $stay->room?->room_no ? $stay->room->room_no . ' — ' : '' }}{{ $stay->guest_name }}
            </option>
        @endforeach
    </select>
</x-field>

<x-field label="Name" name="guest_name" required>
    <x-input name="guest_name" :value="$row->guest_name" data-guest-name />
</x-field>

<x-field label="Mobile" name="mobile">
    <x-input name="mobile" :value="$row->mobile" data-guest-mobile />
</x-field>

<x-field label="Room" name="room_no" help="Filled in from the stay when one is picked.">
    <x-input name="room_no" :value="$row->room_no" data-guest-room />
</x-field>
