@extends('layouts.app')

@section('title', 'My profile')

@section('content')
    <x-page-header title="My profile" subtitle="Your own details. Ask an administrator to change your role or branch."
                   :crumbs="['Home' => url('/'), 'My profile']">
        <x-slot:actions>
            <a href="{{ route('password.change') }}" class="nv-btn nv-btn-outline">
                <x-icon name="lock" /> Change password
            </a>
        </x-slot:actions>
    </x-page-header>

    <x-card>
        <div style="display:flex;flex-wrap:wrap;align-items:center;gap:18px">
            <x-avatar :name="$user->name ?: $user->username" size="lg" />
            <div style="flex:1;min-width:200px">
                <h2 style="font-size:19px;font-weight:700;letter-spacing:-.02em">{{ $user->name ?: $user->username }}</h2>
                <p class="nv-muted" style="font-size:13.5px">{{ $user->role_name }} · {{ $user->branch?->branch_name ?? 'No branch' }}</p>

                <div style="display:flex;gap:8px;margin-top:10px;flex-wrap:wrap">
                    <x-badge :tone="$user->isActive() ? 'success' : 'danger'">{{ $user->isActive() ? 'Active' : 'Inactive' }}</x-badge>
                    <x-badge plain>{{ '@' . $user->username }}</x-badge>
                    <x-badge plain>Joined {{ $user->created_at?->format('M Y') }}</x-badge>
                </div>
            </div>
        </div>
    </x-card>

    <div class="nv-grid nv-grid-form nv-mt">
        <div class="nv-form-aside">
            <h3>Details</h3>
            <p>Keep your contact details current — they are shown to your team.</p>
        </div>

        <form method="POST" action="{{ route('profile-update') }}">
            @csrf

            <x-card>
                <div class="nv-form-grid">
                    <x-field label="Full name" name="name" required>
                        <x-input name="name" :value="$user->name" />
                    </x-field>

                    <x-field label="Mobile" name="mobile" required>
                        <x-input name="mobile" :value="$user->mobile" />
                    </x-field>

                    <x-field label="Gender" name="gender">
                        <x-select name="gender" :options="['male' => 'Male', 'female' => 'Female', 'transgender' => 'Transgender', 'group' => 'Group']"
                                  :selected="$user->gender" placeholder="—" />
                    </x-field>

                    <x-field label="Date of birth" name="dob">
                        <x-input name="dob" type="date" :value="$user->dob?->format('Y-m-d')" />
                    </x-field>

                    <x-field label="Address" name="address" wide>
                        <x-input name="address" :value="$user->address" />
                    </x-field>

                    <x-field label="City" name="city">
                        <x-input name="city" :value="$user->city" />
                    </x-field>

                    <x-field label="State" name="state">
                        <x-input name="state" :value="$user->state" />
                    </x-field>

                    <x-field label="Country" name="country">
                        <x-input name="country" :value="$user->country" />
                    </x-field>

                    <x-field label="Pin code" name="pincode">
                        <x-input name="pincode" :value="$user->pincode" />
                    </x-field>
                </div>

                <x-slot:footer>
                    <div class="nv-actions" style="justify-content:flex-end">
                        <button type="submit" class="nv-btn nv-btn-primary"><x-icon name="check" /> Save changes</button>
                    </div>
                </x-slot:footer>
            </x-card>
        </form>
    </div>
@endsection
