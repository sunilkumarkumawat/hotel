@extends('layouts.print')

@section('title', 'Table card — ' . $table->name)

@section('content')
    {{--
        The card that stands on the table.

        The QR is drawn by App\Support\Qr, in PHP, with nothing installed and no
        call to anybody's server: a hotel's table cards must not stop working
        because an image service went away or the property's internet is down.

        The code under it is not decoration. A phone that will not scan — a
        cracked lens, a camera the guest has not granted — still gets the guest
        to the same place by typing it.
    --}}
    <div class="pr-card">
        <p class="pr-card-kicker">{{ $table->outlet?->name }}</p>

        <h1 class="pr-card-name">{{ $table->group?->kindLabel() ?? 'Table' }} {{ $table->name }}</h1>

        <p class="pr-card-lead">Scan to see the menu and order from your phone</p>

        <div class="pr-card-qr">{!! $qr !!}</div>

        <p class="pr-card-code">{{ $code }}</p>

        <p class="pr-card-url">{{ $url }}</p>

        <p class="pr-card-foot">
            Trouble scanning? Ask us and we will take your order the usual way.
        </p>
    </div>
@endsection

@push('styles')
    <style>
        .pr-card { width: 105mm; max-width: 100%; margin: 0 auto; padding: 12mm 8mm; text-align: center;
                   border: 2px solid #111; border-radius: 6mm; }
        .pr-card-kicker { font-size: 12px; letter-spacing: .18em; text-transform: uppercase; margin: 0 0 2mm; }
        .pr-card-name { font-size: 30px; margin: 0 0 3mm; letter-spacing: -.01em; }
        .pr-card-lead { font-size: 13px; margin: 0 0 6mm; }
        .pr-card-qr { display: flex; justify-content: center; margin-bottom: 5mm; }
        .pr-card-qr svg { width: 55mm; height: 55mm; }
        .pr-card-code { font-family: "Courier New", monospace; font-size: 22px; letter-spacing: .3em;
                        font-weight: 700; margin: 0 0 2mm; }
        .pr-card-url { font-size: 10px; word-break: break-all; margin: 0 0 6mm; color: #444; }
        .pr-card-foot { font-size: 11px; margin: 0; }

        @media print {
            .pr-sheet { padding: 0; box-shadow: none; }
            @page { margin: 8mm; }
        }
    </style>
@endpush
