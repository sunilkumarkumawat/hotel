@if ($errors->any())
    <div style="margin-bottom:18px">
        <x-alert tone="danger" title="Please check the form">{{ $errors->first() }}</x-alert>
    </div>
@endif

<div class="nv-grid nv-grid-form">
    <div class="nv-form-aside">
        <h3>Role</h3>
        <p>A short name your team will recognise — Manager, Receptionist, Night Auditor.</p>
        <p style="margin-top:10px">
            A role is a label. What someone may actually open is ticked per user on the
            <a href="{{ route('users.index') }}" style="color:var(--nv-primary);font-weight:600">Users</a> screen.
        </p>
    </div>

    <x-card>
        <x-field label="Role name" name="name" required>
            <x-input name="name" :value="$role->name" placeholder="Front Desk Supervisor" />
        </x-field>

        <x-field label="Description" name="description" help="What this role is responsible for.">
            <x-input name="description" :value="$role->description" placeholder="Runs the front desk during the evening shift." />
        </x-field>
    </x-card>
</div>

<div class="nv-actions" style="justify-content:flex-end;margin-top:22px">
    <a href="{{ route('role.index') }}" class="nv-btn nv-btn-outline">Cancel</a>
    <button type="submit" class="nv-btn nv-btn-primary">
        <x-icon name="check" /> {{ $role->exists ? 'Save changes' : 'Create role' }}
    </button>
</div>
