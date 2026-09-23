@extends('layouts.app')

@section('title', 'Notifications')

@section('content')
    <x-page-header
        title="Notifications"
        subtitle="Everything that has happened in this branch, newest first."
        :crumbs="['Home' => url('/'), 'Notifications']"
    >
        <x-slot:actions>
            @if ($unread > 0)
                <form method="POST" action="{{ route('notifications.read') }}">
                    @csrf
                    <button type="submit" class="nv-btn nv-btn-soft">
                        <x-icon name="check" /> Mark all read ({{ $unread }})
                    </button>
                </form>
            @endif

            @canView('notification-settings')
                <a href="{{ route('notification-settings') }}" class="nv-btn nv-btn-outline">
                    <x-icon name="cog" /> Settings
                </a>
            @endCanView
        </x-slot:actions>
    </x-page-header>

    <div class="nv-mt">
        <x-card>
            <form method="GET" class="nv-toolbar">
                <select name="event" class="nv-select" style="width:240px" aria-label="Event">
                    <option value="">Everything</option>
                    @foreach ($events as $key => $label)
                        <option value="{{ $key }}" @selected($filters['event'] === $key)>{{ $label }}</option>
                    @endforeach
                </select>

                <select name="level" class="nv-select" style="width:150px" aria-label="Importance">
                    <option value="">Any importance</option>
                    @foreach (['info' => 'Information', 'success' => 'Good news', 'warning' => 'Watch out', 'danger' => 'Bad news'] as $key => $label)
                        <option value="{{ $key }}" @selected($filters['level'] === $key)>{{ $label }}</option>
                    @endforeach
                </select>

                <label class="nv-check">
                    <input type="checkbox" name="unread" value="1" @checked($filters['unread']) />
                    <span>Unread only</span>
                </label>

                <button type="submit" class="nv-btn nv-btn-primary"><x-icon name="filter" /> Show</button>
                <a href="{{ route('notifications.index') }}" class="nv-btn nv-btn-ghost">Reset</a>
            </form>
        </x-card>
    </div>

    <div class="nv-mt">
        <x-card flush>
            @if ($rows->isEmpty())
                <div class="nv-empty">
                    <span class="nv-empty-icon"><x-icon name="bell" /></span>
                    <strong>Nothing here</strong>
                    <p>Notifications show up as things happen — a booking, a check-in, a bill.</p>
                </div>
            @else
                <ul class="nv-notif-list">
                    @foreach ($rows as $row)
                        <li @class(['nv-notif', 'is-unread' => $row->isUnread(), 'is-' . $row->level])>
                            <span class="nv-notif-icon"><x-icon :name="$row->icon ?: 'bell'" /></span>

                            <div class="nv-notif-body">
                                <p class="nv-notif-title">
                                    @if ($row->url)
                                        <a href="{{ $row->url }}">{{ $row->title }}</a>
                                    @else
                                        {{ $row->title }}
                                    @endif
                                </p>

                                @if ($row->body)
                                    <p class="nv-notif-text">{{ $row->body }}</p>
                                @endif

                                <p class="nv-notif-meta">
                                    {{ event_label($row->event) }}
                                    · {{ $row->created_at?->format('d M Y, h:i A') }}
                                    @if ($row->creator)
                                        · {{ $row->creator->name }}
                                    @endif
                                </p>
                            </div>

                            @if ($row->isUnread())
                                <form method="POST" action="{{ route('notifications.read') }}" class="nv-notif-action">
                                    @csrf
                                    <input type="hidden" name="id" value="{{ $row->id }}" />
                                    <button type="submit" class="nv-icon-btn" title="Mark as read">
                                        <x-icon name="check" />
                                    </button>
                                </form>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif

            @if ($rows->hasPages())
                <x-slot:footer>{{ $rows->links() }}</x-slot:footer>
            @endif
        </x-card>
    </div>
@endsection
