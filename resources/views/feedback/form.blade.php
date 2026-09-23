@extends('layouts.public')

@section('title', 'How was your stay?')

@php
    $checkIn = $feedback->checkIn;

    // The first name if we have it; otherwise whatever the booking was in.
    $name = trim((string) ($feedback->guest?->first_name ?: ''));

    if ($name === '' && $checkIn?->guest_name) {
        $parts = preg_split('/\s+/', trim($checkIn->guest_name));
        // Skip a title — "Mr." is not what anybody wants to be called.
        $name = count($parts) > 1 && str_ends_with($parts[0], '.') ? $parts[1] : $parts[0];
    }
@endphp

@section('content')
<div class="nv-fb">
    <div class="nv-fb-card">
        <div class="nv-fb-head">
            <p class="nv-fb-hotel">{{ $hotel?->legal_name ?: ($hotel?->branch_name ?: config('app.name')) }}</p>

            @if ($done)
                <h1>Thank you</h1>
                <p>Your answers are with the manager.</p>
            @else
                <h1>How was your stay{{ $name ? ', ' . $name : '' }}?</h1>
                <p>
                    @if ($checkIn)
                        {{ $checkIn->checkin_date->format('d M') }} –
                        {{ \Carbon\CarbonImmutable::parse($checkIn->departsOn())->format('d M Y') }}{{ $checkIn->room?->room_no ? ' · Room ' . $checkIn->room->room_no : '' }}.
                    @endif
                    Two minutes, and every question is optional.
                </p>
            @endif
        </div>

        @if ($done)
            {{--
                Their own answers, given back to them. A bare "thanks, we got
                it" leaves a guest wondering whether it sent at all.
            --}}
            <div class="nv-fb-body">
                @if ($feedback->overall)
                    <div class="nv-fb-done-score">
                        <span class="nv-fb-shown">
                            @for ($i = 1; $i <= 5; $i++)
                                <i @class(['is-on' => $i <= $feedback->overall])>★</i>
                            @endfor
                        </span>
                        <span>{{ $feedback->overall }} out of 5 overall</span>
                    </div>
                @endif

                @if ($feedback->liked)
                    <p class="nv-fb-said"><strong>What you liked</strong>{{ $feedback->liked }}</p>
                @endif

                @if ($feedback->improve)
                    <p class="nv-fb-said"><strong>What we could do better</strong>{{ $feedback->improve }}</p>
                @endif

                <p class="nv-fb-thanks">
                    If something went wrong, somebody here reads this today and will get in touch.
                </p>
            </div>
        @else
            <form method="POST" action="{{ route('guest-feedback.store', $feedback->token) }}" class="nv-fb-body">
                @csrf

                @if (session('error'))
                    <p class="nv-fb-error">{{ session('error') }}</p>
                @endif

                {{--
                    The stars are radio buttons in REVERSE order: 5 first, 1
                    last. That is what lets plain CSS light up a star and every
                    star before it, with no JavaScript at all — the sibling
                    selector can only reach forward.
                --}}
                <div class="nv-fb-block is-main">
                    <span class="nv-fb-label">Overall</span>
                    <div class="nv-fb-stars">
                        @for ($i = 5; $i >= 1; $i--)
                            <input type="radio" name="overall" id="overall-{{ $i }}" value="{{ $i }}"
                                   @checked(old('overall') == $i) />
                            <label for="overall-{{ $i }}" title="{{ $i }} out of 5"><span>★</span></label>
                        @endfor
                    </div>
                </div>

                @foreach ($areas as $key => $label)
                    <div class="nv-fb-block">
                        <span class="nv-fb-label">{{ $label }}</span>
                        <div class="nv-fb-stars is-small">
                            @for ($i = 5; $i >= 1; $i--)
                                <input type="radio" name="{{ $key }}" id="{{ $key }}-{{ $i }}" value="{{ $i }}"
                                       @checked(old($key) == $i) />
                                <label for="{{ $key }}-{{ $i }}" title="{{ $i }} out of 5"><span>★</span></label>
                            @endfor
                        </div>
                    </div>
                @endforeach

                <div class="nv-fb-block is-text">
                    <label class="nv-fb-label" for="liked">What did you like?</label>
                    <textarea id="liked" name="liked" rows="3" maxlength="2000"
                              placeholder="Anything at all">{{ old('liked') }}</textarea>
                </div>

                <div class="nv-fb-block is-text">
                    <label class="nv-fb-label" for="improve">What could we do better?</label>
                    <textarea id="improve" name="improve" rows="3" maxlength="2000"
                              placeholder="We would rather hear it here than read it online">{{ old('improve') }}</textarea>
                </div>

                <div class="nv-fb-block is-return">
                    <span class="nv-fb-label">Would you stay with us again?</span>
                    <div class="nv-fb-choice">
                        <input type="radio" name="would_return" id="return-yes" value="1"
                               @checked(old('would_return') === '1') />
                        <label for="return-yes">Yes</label>

                        <input type="radio" name="would_return" id="return-no" value="0"
                               @checked(old('would_return') === '0') />
                        <label for="return-no">No</label>
                    </div>
                </div>

                <button type="submit" class="nv-fb-send">Send</button>

                <p class="nv-fb-fine">Only the hotel sees this. It is not published anywhere.</p>
            </form>
        @endif
    </div>

    <p class="nv-fb-foot">
        {{ $hotel?->branch_name }}{{ $hotel?->mobile_number ? ' · ' . $hotel->mobile_number : '' }}
    </p>
</div>
@endsection
