@extends('layouts.app')

@section('title', 'Reports')

@section('content')
    <x-page-header
        title="Reports"
        subtitle="Every question the system can answer, in one place."
        :crumbs="['Home' => url('/'), 'Reports']"
    />

    <div class="nv-mt">
        <x-card>
            <p class="nv-help" style="margin:0">
                Each report opens with this month's dates and can be narrowed from there, and every one of
                them exports to a spreadsheet. Nothing here stores a total — the figures are read from the
                same rows the screens are, which is why a report can never disagree with the folio it came
                from.
            </p>
        </x-card>
    </div>

    @foreach ($groups as $key => $groupName)
        @php $list = $reports->get($key, collect()); @endphp

        @continue($list->isEmpty())

        <div class="nv-mt">
            <x-card :title="$groupName" flush>
                <div class="nv-rep-grid">
                    @foreach ($list as $report)
                        <a href="{{ route('reports.show', $report['slug']) }}" class="nv-rep-tile">
                            <span class="nv-rep-icon"><x-icon :name="$report['icon']" /></span>

                            <span class="nv-rep-text">
                                <strong>{{ $report['label'] }}</strong>
                                <small>{{ $report['about'] }}</small>
                            </span>

                            <span class="nv-rep-go"><x-icon name="chevron-right" /></span>
                        </a>
                    @endforeach
                </div>
            </x-card>
        </div>
    @endforeach
@endsection
