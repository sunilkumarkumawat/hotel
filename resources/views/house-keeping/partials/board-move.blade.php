{{--
    One button, one move. A plain form on purpose: the board works with the
    script switched off, which matters because a housekeeping terminal is
    exactly the machine whose browser is eight years old.
--}}
<form method="POST" action="{{ route('house-keeping.board.move') }}">
    @csrf
    <input type="hidden" name="room_id" value="{{ $room }}" />
    <input type="hidden" name="to" value="{{ $to }}" />
    <button type="submit" class="nv-btn nv-btn-ghost nv-btn-sm">{{ $label }}</button>
</form>
