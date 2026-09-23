@extends('layouts.app')

@php
    $editing = (bool) $order->exists;
    $roomId = old('room_id', $order->room_id ?? $preselect);
    $blocked = old('block_room', $order->room_block_id ? 1 : 0);
@endphp

@section('title', $editing ? 'Edit Work Order' : 'Add Work Order')

@section('content')
    <x-page-header
        :title="$editing ? 'Edit Work Order' : 'Add Work Order'"
        :subtitle="$orderNo . ($editing ? ' · ' . $order->title : ' · a maintenance job card')"
        :crumbs="['Home' => url('/'), 'House Keeping', 'Work Order' => route('house-keeping.work-order'), $editing ? 'Edit' : 'Add']"
    >
        <x-slot:actions>
            <a href="{{ route('house-keeping.work-order') }}" class="nv-btn nv-btn-outline">Cancel</a>

            <button type="submit" form="wo-form" class="nv-btn nv-btn-primary">
                <x-icon name="check" /> Save
            </button>
        </x-slot:actions>
    </x-page-header>

    @if (session('error'))
        <div class="nv-mt"><x-alert tone="warning" title="Saved, with one thing to know">{{ session('error') }}</x-alert></div>
    @endif

    @if ($errors->any())
        <div class="nv-mt">
            <x-alert tone="danger" title="Please fix {{ $errors->count() }} thing(s)">{{ $errors->first() }}</x-alert>
        </div>
    @endif

    <form method="POST" id="wo-form"
          action="{{ $editing ? route('house-keeping.work-order.update', $order) : route('house-keeping.work-order.store') }}">
        @csrf
        @if ($editing) @method('PUT') @endif

        <div class="nv-mt">
            <x-card>
                <div class="nv-form-grid nv-grid-4">
                    <x-field label="Order No.">
                        <x-input :value="$orderNo" readonly />
                    </x-field>

                    <x-field label="Start Date" name="start_date" required>
                        <x-input name="start_date" type="date"
                                 :value="old('start_date', $order->start_date?->toDateString() ?? $today)" />
                    </x-field>

                    <x-field label="Time" name="start_time" help="When the work is expected to begin.">
                        <x-input name="start_time" type="time"
                                 :value="old('start_time', $order->start_time ? substr($order->start_time, 0, 5) : $now)" />
                    </x-field>

                    <x-field label="End Date" name="end_date" required help="The last day the work runs.">
                        <x-input name="end_date" type="date"
                                 :value="old('end_date', $order->end_date?->toDateString() ?? $today)" />
                    </x-field>

                    <x-field label="Unit/Room" name="room_id"
                             help="Leave on Common area for a lift, a corridor or the lobby.">
                        <select name="room_id" id="room_id" class="nv-select">
                            <option value="">Common area (not a room)</option>
                            @foreach ($rooms as $room)
                                <option value="{{ $room->id }}" @selected((string) $roomId === (string) $room->id)>
                                    {{ $room->room_no }}@if ($room->floor) — Floor {{ $room->floor }}@endif
                                </option>
                            @endforeach
                        </select>
                    </x-field>

                    <x-field label="Due Date" name="due_date" required help="The deadline the list chases.">
                        <x-input name="due_date" type="date"
                                 :value="old('due_date', $order->due_date?->toDateString() ?? $today)" />
                    </x-field>

                    <x-field label="Category" name="category" required>
                        <x-select name="category" :options="$categories" :selected="old('category', $order->category)" />
                    </x-field>

                    <x-field label="Priority" name="priority" required>
                        <x-select name="priority" :options="$priorities" :selected="old('priority', $order->priority)" />
                    </x-field>

                    <x-field label="Status" name="status" required>
                        <x-select name="status" :options="$statuses" :selected="old('status', $order->status)" />
                    </x-field>

                    <x-field label="Assign To" name="assigned_to"
                             help="Leave blank and it sits in the list as nobody's job.">
                        <x-select name="assigned_to" :options="$employees->all()"
                                  :selected="old('assigned_to', $order->assigned_to)" placeholder="Select Employee" />
                    </x-field>

                    <x-field label="Job Notes" name="description" required wide
                             help="What is wrong, and anything the person doing it needs to know.">
                        {{-- x-textarea applies old() itself, so the stored value
                             is handed in rather than resolved here. --}}
                        <x-textarea name="description" rows="4" :value="$order->description"
                                    placeholder="AC not cooling — compressor tripping after ten minutes." />
                    </x-field>
                </div>
            </x-card>
        </div>

        {{-- ── The join to Room Blocked ──────────────────────────────────── --}}
        <div class="nv-mt">
            <x-card title="Take the room off sale"
                    subtitle="Only for work a guest cannot be in the room for.">
                <label class="nv-check-inline">
                    <input type="hidden" name="block_room" value="0" />
                    <input type="checkbox" name="block_room" value="1" class="nv-check" @checked($blocked) />
                    Block this room while the job runs
                </label>

                <p class="nv-help">
                    Ticked, the job writes a <b>maintenance block</b> for its own Start and End dates: the room
                    is marked <b>Repair</b> in house keeping and stops appearing on the tape chart, the status
                    view and the booking form. Closing or deleting the job puts it back on sale.
                    A dripping tap needs no block — a guest can stay in the room while it is fixed.
                </p>

                @if ($order->room_block_id && $order->block)
                    <p class="nv-help">
                        Right now it holds <b>{{ $order->block->range }}</b> on room
                        <b>{{ $order->room?->room_no }}</b>. Change the dates above and the block follows.
                    </p>
                @endif

                <p class="nv-help">
                    A room somebody has already booked cannot be held — the job still saves, and the screen
                    says so rather than pretending the room is off sale.
                </p>
            </x-card>
        </div>
    </form>
@endsection
