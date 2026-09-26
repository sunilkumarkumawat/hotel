@extends('layouts.app')

@section('title', 'Edit ' . ($user->name ?: $user->username))

@section('content')
    <x-page-header
        :title="'Edit ' . ($user->name ?: $user->username)"
        :subtitle="'Joined ' . $user->created_at?->format('d M Y') . ' · ' . $user->role_name"
        :crumbs="['Home' => url('/'), 'Users' => route('users.index'), 'Edit']"
    >
        <x-slot:actions>
            <form method="POST" action="{{ route('users.destroy', $user) }}"
                  data-confirm="{{ $user->name ?: $user->username }} will lose access and the account will be removed."
                  data-confirm-title="Delete user?"
                  data-confirm-action="Delete user">
                @csrf
                @method('DELETE')
                <button type="submit" class="nv-btn nv-btn-outline">
                    <x-icon name="trash" /> Delete
                </button>
            </form>
        </x-slot:actions>
    </x-page-header>

    <form method="POST" action="{{ route('users.update', $user) }}">
        @csrf
        @method('PUT')
        @include('users._form')
    </form>
@endsection
