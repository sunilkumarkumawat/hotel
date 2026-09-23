@extends('layouts.app')

@section('title', 'Change password')

@section('content')
    <x-page-header title="Change password" subtitle="Pick something at least 8 characters long."
                   :crumbs="['Home' => url('/'), 'Change password']" />

    <div class="nv-grid nv-grid-form">
        <div class="nv-form-aside">
            <h3>Security</h3>
            <p>You will stay signed in on this device after changing it.</p>
        </div>

        <form method="POST" action="{{ route('password.update') }}">
            @csrf

            <x-card>
                @if ($errors->any())
                    <div style="margin-bottom:16px">
                        <x-alert tone="danger" title="Please check the form">{{ $errors->first() }}</x-alert>
                    </div>
                @endif

                <x-field label="Current password" name="current_password" required>
                    <x-input name="current_password" type="password" autocomplete="current-password" />
                </x-field>

                <div class="nv-form-grid">
                    <x-field label="New password" name="password" required>
                        <x-input name="password" type="password" autocomplete="new-password" />
                    </x-field>

                    <x-field label="Confirm new password" name="password_confirmation" required>
                        <x-input name="password_confirmation" type="password" autocomplete="new-password" />
                    </x-field>
                </div>

                <x-slot:footer>
                    <div class="nv-actions" style="justify-content:flex-end">
                        <button type="submit" class="nv-btn nv-btn-primary"><x-icon name="check" /> Change password</button>
                    </div>
                </x-slot:footer>
            </x-card>
        </form>
    </div>
@endsection
