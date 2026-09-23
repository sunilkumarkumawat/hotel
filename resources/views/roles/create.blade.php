@extends('layouts.app')

@section('title', 'New role')

@section('content')
    <x-page-header title="New role" :crumbs="['Home' => url('/'), 'Roles' => route('role.index'), 'New role']" />

    <form method="POST" action="{{ route('role.store') }}">
        @csrf
        @include('roles._form')
    </form>
@endsection
