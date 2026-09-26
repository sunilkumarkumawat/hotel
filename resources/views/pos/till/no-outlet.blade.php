@extends('layouts.app')

@section('title', 'POS')

@section('content')
    <x-page-header
        title="Point Of Sale"
        subtitle="A till needs an outlet before it can sell anything"
        :crumbs="['Home' => url('/'), 'Point Of Sale' => route('point-of-sale.dashboard'), 'POS']"
    />

    <div class="nv-mt">
        <x-card>
            <div class="nv-empty">
                <span class="nv-empty-icon"><x-icon name="inbox" /></span>
                <strong>No outlet you can bill through</strong>
                <p>
                    Either none has been set up yet, or the ones that exist name other people as
                    their users. An outlet is the restaurant, the bar, room service — it carries the
                    bill series, the tax numbers and what prints on a receipt.
                </p>

                @canView('point-of-sale/setup/outlets')
                    <a href="{{ route('point-of-sale.setup.outlets') }}" class="nv-btn nv-btn-primary">
                        <x-icon name="plus" /> Set up an outlet
                    </a>
                @endCanView
            </div>
        </x-card>
    </div>
@endsection
