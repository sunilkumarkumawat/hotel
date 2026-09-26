@extends('layouts.app')

@section('title', 'Add branch')

@section('content')
    <x-page-header title="Add branch" :crumbs="['Home' => url('/'), 'Branches' => route('viewBranch.index'), 'Add branch']" />

    <form method="POST" action="{{ route('viewBranch.store') }}">
        @csrf
        @include('branches._form')
    </form>
@endsection
