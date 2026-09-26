@extends('layouts.app')

@section('title', 'Edit ' . $role->name)

@section('content')
    <x-page-header :title="'Edit ' . $role->name"
                   :crumbs="['Home' => url('/'), 'Roles' => route('role.index'), 'Edit']" />

    <form method="POST" action="{{ route('role.update', $role) }}">
        @csrf
        @method('PUT')
        @include('roles._form')
    </form>
@endsection
