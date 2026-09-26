@extends('layouts.app')

@section('title', 'Sign-in History')

@section('content')
    <x-page-header
        title="Sign-in History"
        subtitle="Who signed in, from where, and who tried and failed."
        :crumbs="['Home' => url('/'), 'Shift & Audit', 'Sign-ins']"
    >
        <x-slot:actions>
            @canView('audit/trail')
                <a href="{{ route('audit.trail') }}" class="nv-btn nv-btn-outline">
                    <x-icon name="shield" /> Audit trail
                </a>
            @endCanView
        </x-slot:actions>
    </x-page-header>

    {{-- ── Refusals first: that is what this screen is for ───────────────── --}}
    @if ($failed->isNotEmpty())
        <div class="nv-mt">
            <x-alert :tone="$failed->count() >= 10 ? 'danger' : 'warning'"
                     title="{{ $failed->count() }} refused {{ \Illuminate\Support\Str::plural('sign-in', $failed->count()) }} in this window">
                A handful is somebody who has forgotten their password. A run of them against one name
                from one address, at an hour when the hotel is quiet, is something else.
            </x-alert>
        </div>

        @if ($suspects->isNotEmpty())
            <div class="nv-mt">
                <x-card title="Most tried" subtitle="Username and where the attempt came from.">
                    <div class="nv-au-suspects">
                        @foreach ($suspects as $who => $count)
                            <div>
                                <span>{{ $who }}</span>
                                <b @class(['is-hot' => $count >= 5])>{{ $count }}
                                    {{ \Illuminate\Support\Str::plural('try', $count) }}</b>
                            </div>
                        @endforeach
                    </div>
                </x-card>
            </div>
        @endif
    @endif

    <div class="nv-mt">
        <x-card flush>
            <form method="GET" class="nv-toolbar">
                <x-field label="From" name="from"><x-input type="date" name="from" :value="$from" /></x-field>
                <x-field label="To" name="to"><x-input type="date" name="to" :value="$to" /></x-field>

                <x-field label="Who" name="user">
                    <select name="user" class="nv-select">
                        <option value="">Everybody</option>
                        @foreach ($users as $id => $name)
                            <option value="{{ $id }}" @selected((int) $filters['user'] === (int) $id)>{{ $name }}</option>
                        @endforeach
                    </select>
                </x-field>

                <x-field label="Show" name="action">
                    <select name="action" class="nv-select">
                        <option value="">Everything</option>
                        <option value="login" @selected($filters['action'] === 'login')>Signed in</option>
                        <option value="logout" @selected($filters['action'] === 'logout')>Signed out</option>
                        <option value="login_failed" @selected($filters['action'] === 'login_failed')>Refused</option>
                    </select>
                </x-field>

                <button type="submit" class="nv-btn nv-btn-primary"><x-icon name="search" /> Show</button>
            </form>

            @if ($rows->isEmpty())
                <div class="nv-empty">
                    <span class="nv-empty-icon"><x-icon name="lock" /></span>
                    <strong>Nothing in this window</strong>
                    <p>Nobody signed in or out between these two dates.</p>
                </div>
            @else
                <div class="nv-table-wrap">
                    <table class="nv-table">
                        <thead>
                            <tr>
                                <th>When</th>
                                <th>What</th>
                                <th>Who</th>
                                <th>Username tried</th>
                                <th>Address</th>
                                <th>Browser</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($rows as $row)
                                <tr @class(['nv-au-refused' => $row->action === 'login_failed'])>
                                    <td class="nv-au-when">
                                        {{ $row->happened_at?->format('d M, h:i A') }}
                                        <span class="nv-sub">{{ $row->happened_at?->diffForHumans() }}</span>
                                    </td>
                                    <td>
                                        <span @class(['nv-au-action', 'is-' . $row->tone])>{{ $row->action_label }}</span>
                                    </td>
                                    <td>{{ $row->user_name ?: '—' }}</td>
                                    <td>{{ $row->subject_label ?: '—' }}</td>
                                    <td>{{ $row->ip ?: '—' }}</td>
                                    <td class="nv-au-agent">{{ \Illuminate\Support\Str::limit($row->agent, 60) ?: '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="nv-card-foot">{{ $rows->links() }}</div>
            @endif
        </x-card>
    </div>

    <div class="nv-mt">
        <x-alert tone="info" title="The password is never recorded">
            A refused sign-in stores the username that was typed and where it came from. It does not
            store what was typed into the password box — a typed password in a log is a password in a
            log, whether or not it was the right one.
        </x-alert>
    </div>
@endsection
