@extends('layouts.app')

@section('title', 'Add user')

@section('content')
    <x-page-header
        title="Add user"
        subtitle="Create the account and tick exactly what it may reach."
        :crumbs="['Home' => url('/'), 'Users' => route('users.index'), 'Add user']"
    />

    <form method="POST" action="{{ route('users.store') }}">
        @csrf
        @include('users._form')
    </form>
@endsection
