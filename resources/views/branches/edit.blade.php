@extends('layouts.app')

@section('title', 'Edit ' . $branch->branch_name)

@section('content')
    <x-page-header :title="'Edit ' . $branch->branch_name"
                   :crumbs="['Home' => url('/'), 'Branches' => route('viewBranch.index'), 'Edit']">
        <x-slot:actions>
            <form method="POST" action="{{ route('viewBranch.destroy', $branch) }}"
                  data-confirm="{{ $branch->branch_name }} will be removed. Users must be moved to another branch first."
                  data-confirm-title="Delete branch?"
                  data-confirm-action="Delete branch">
                @csrf
                @method('DELETE')
                <button type="submit" class="nv-btn nv-btn-outline"><x-icon name="trash" /> Delete</button>
            </form>
        </x-slot:actions>
    </x-page-header>

    <form method="POST" action="{{ route('viewBranch.update', $branch) }}">
        @csrf
        @method('PUT')
        @include('branches._form')
    </form>
@endsection
