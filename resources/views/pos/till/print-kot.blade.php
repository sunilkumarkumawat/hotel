@extends('layouts.print')

@section('title', 'KOT ' . $number . ' — ' . $order->where_label)

@php
    // One ticket per department: the bar does not need to read the curry.
    $byDepartment = $lines->groupBy(fn ($line) => $line->department?->name ?: 'Kitchen');
@endphp

@section('content')
    @foreach ($byDepartment as $department => $group)
        <div class="pr-kot" style="--roll:{{ (int) ($outlet->page_width ?? 80) }}mm">
            <div class="pr-kot-head">
                <strong>{{ $department }}</strong>
                <span>KOT {{ $number }} · {{ $order->order_no }}</span>
            </div>

            <div class="pr-kot-where">
                <b>{{ $order->where_label }}</b>
                <span>{{ $group->first()->fired_at?->format('d/m h:i A') }}</span>
            </div>

            <div class="pr-kot-meta">
                <span>{{ $order->pax }} pax</span>
                @if ($order->steward)
                    <span>{{ $order->steward->name }}</span>
                @endif
            </div>

            {{--
                Big type and nothing else. A kitchen ticket is read across a
                hot pass at arm's length, so prices, taxes and totals are
                deliberately absent — they are not the cook's business and they
                are in the way.
            --}}
            <table class="pr-kot-lines">
                @foreach ($group as $line)
                    <tr>
                        <td class="is-qty">{{ rtrim(rtrim(number_format((float) $line->qty, 2, '.', ''), '0'), '.') }}</td>
                        <td>
                            {{ $line->item_name }}
                            @if ($line->has_modifiers && $line->modifiers->isNotEmpty())
                                <i>{{ $line->modifiers->pluck('name')->implode(', ') }}</i>
                            @endif
                            @if ($line->remark)
                                <i>{{ $line->remark }}</i>
                            @endif
                            @if ($line->is_nc)
                                <i>NO CHARGE</i>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </table>

            @if ($order->remark)
                <p class="pr-kot-note">{{ $order->remark }}</p>
            @endif
        </div>
    @endforeach
@endsection

@push('styles')
    <style>
        .pr-kot { width: var(--roll); max-width: 100%; margin: 0 auto 10mm; padding: 4mm;
                  page-break-after: always; }
        .pr-kot:last-child { page-break-after: auto; margin-bottom: 0; }
        .pr-kot-head { text-align: center; border-bottom: 2px solid #000; padding-bottom: 4px; }
        .pr-kot-head strong { display: block; font-size: 18px; letter-spacing: .06em; text-transform: uppercase; }
        .pr-kot-head span { font-size: 11px; }
        .pr-kot-where { display: flex; justify-content: space-between; align-items: baseline;
                        margin: 6px 0 2px; font-size: 12px; }
        .pr-kot-where b { font-size: 20px; }
        .pr-kot-meta { display: flex; gap: 10px; font-size: 11px; border-bottom: 1px dashed #000; padding-bottom: 4px; }
        .pr-kot-lines { width: 100%; border-collapse: collapse; margin-top: 6px; }
        .pr-kot-lines td { padding: 5px 2px; border-bottom: 1px dotted #999; font-size: 15px; vertical-align: top; }
        .pr-kot-lines .is-qty { width: 2.5em; font-size: 20px; font-weight: 700; text-align: right; padding-right: 8px; }
        .pr-kot-lines i { display: block; font-size: 12px; font-style: normal; font-weight: 700; text-transform: uppercase; }
        .pr-kot-note { font-size: 12px; font-weight: 700; margin-top: 6px; border-top: 1px dashed #000; padding-top: 4px; }

        @media print {
            .pr-sheet { padding: 0; box-shadow: none; width: auto; }
            @page { margin: 0; }
        }
    </style>
@endpush
