@php
    /** @var \App\Models\User $user */
    /** @var array<string, array<string, int>> $granted */
    $actions = ['view' => 'View', 'add' => 'Add', 'edit' => 'Edit', 'delete' => 'Delete'];
    $granted = old('permissions', $granted) ?? [];

    $total = $menus->sum(fn ($m) => $m->submodules->count()) * count($actions);
    $selected = collect($granted)->sum(fn ($row) => count(array_filter((array) $row)));
@endphp

@if ($errors->any())
    <div style="margin-bottom:18px">
        <x-alert tone="danger" title="Please fix {{ $errors->count() }} field(s)">
            {{ $errors->first() }}
        </x-alert>
    </div>
@endif

<div class="nv-grid nv-grid-form">
    <div class="nv-form-aside">
        <h3>Account</h3>
        <p>The username is what they sign in with. Mobile is used for contact only.</p>
    </div>

    <x-card>
        <div class="nv-form-grid">
            <x-field label="Full name" name="name" required>
                <x-input name="name" :value="$user->name" placeholder="Aarav Mehta" />
            </x-field>

            <x-field label="Username" name="username" required help="Letters, numbers and . _ @ - only.">
                <x-input name="username" :value="$user->username" placeholder="aarav" autocomplete="username" />
            </x-field>

            <x-field label="Mobile" name="mobile" required>
                <x-input name="mobile" :value="$user->mobile" placeholder="98765 43210" />
            </x-field>

            {{-- Where notifications reach this person. Both are optional: a
                 user with neither still sees the bell in the top bar, which is
                 the channel nobody has to set up. --}}
            <x-field label="Email" name="email" help="For the notifications that are switched on for email.">
                <x-input name="email" type="email" :value="$user->email" placeholder="manager@hotel.com" />
            </x-field>

            <x-field label="WhatsApp number" name="whatsapp_no" help="Leave empty and they get no WhatsApp messages.">
                <x-input name="whatsapp_no" :value="$user->whatsapp_no" placeholder="98765 43210" />
            </x-field>

            <x-field label="Notifications" name="notify_mail">
                <label class="nv-check">
                    <input type="hidden" name="notify_web" value="0" />
                    <input type="checkbox" name="notify_web" value="1"
                           @checked(old('notify_web', $user->exists ? $user->notify_web : 1)) />
                    <span>Pop-up alerts in the browser</span>
                </label>

                <label class="nv-check" style="margin-top:5px">
                    <input type="hidden" name="notify_mail" value="0" />
                    <input type="checkbox" name="notify_mail" value="1"
                           @checked(old('notify_mail', $user->notify_mail)) />
                    <span>Email them too</span>
                </label>
            </x-field>

            <x-field label="Role" name="role_id" required help="Administrators bypass every permission check.">
                <x-select name="role_id" :options="$roles->pluck('name', 'id')->all()"
                          :selected="$user->role_id" placeholder="Choose a role…" />
            </x-field>

            <x-field label="Branch" name="branch_id" required help="Permissions below are saved for this branch.">
                <x-select name="branch_id" :options="$branches->pluck('branch_name', 'id')->all()"
                          :selected="$user->branch_id" placeholder="Choose a branch…" />
            </x-field>

            <x-field label="Gender" name="gender">
                <x-select name="gender" :options="['male' => 'Male', 'female' => 'Female', 'transgender' => 'Transgender', 'group' => 'Group']"
                          :selected="$user->gender" placeholder="—" />
            </x-field>

            <x-field label="Date of birth" name="dob">
                <x-input name="dob" type="date" :value="$user->dob?->format('Y-m-d')" />
            </x-field>

            <x-field label="Address" name="address" wide>
                <x-input name="address" :value="$user->address" placeholder="Street, area, city" />
            </x-field>
        </div>

        <hr class="nv-hr" />

        <div class="nv-form-grid">
            <x-field label="Password" name="password"
                     :help="$user->exists ? 'Leave empty to keep the current password.' : 'At least 8 characters.'">
                <x-input name="password" type="password" placeholder="••••••••••" autocomplete="new-password" />
            </x-field>

            <x-field label="Confirm password" name="password_confirmation">
                <x-input name="password_confirmation" type="password" placeholder="••••••••••" autocomplete="new-password" />
            </x-field>
        </div>

        <label class="nv-check">
            <input type="checkbox" name="status" value="1" @checked(old('status', $user->exists ? $user->status : 1)) />
            <span>Account is active — inactive users cannot sign in</span>
        </label>
    </x-card>
</div>

<hr class="nv-hr" />

<div class="nv-grid nv-grid-form">
    <div class="nv-form-aside">
        <h3>Permissions</h3>
        <p>
            Tick what this user may do on each screen. Use the column headings to grant an
            action everywhere, or a module row to grant a whole area at once.
        </p>
        <p style="margin-top:10px">
            Ticking anything switches <strong>View</strong> on automatically — the sidebar
            uses it to decide what to show.
        </p>
    </div>

    <x-card flush>
        <div data-matrix data-matrix-total="{{ $total }}">
            <div class="nv-matrix-bar">
                <label class="nv-check">
                    <input type="checkbox" data-matrix-all />
                    <span><strong>Select everything</strong></span>
                </label>

                <span class="nv-matrix-count">
                    <b data-matrix-selected>{{ $selected }}</b> of {{ $total }} permissions
                </span>
            </div>

            @if ($menus->isEmpty())
                <div class="nv-empty">
                    <span class="nv-empty-icon"><x-icon name="grid" /></span>
                    <strong>No modules yet</strong>
                    <p>Add some on the <a href="{{ route('modules.index') }}" style="color:var(--nv-primary);font-weight:600">Modules</a> screen first.</p>
                </div>
            @else
                <div class="nv-table-wrap">
                    <table class="nv-table nv-matrix">
                        <thead>
                            <tr>
                                <th>Screen</th>
                                @foreach ($actions as $key => $label)
                                    <th class="is-center">
                                        <label title="Toggle {{ $label }} everywhere">
                                            <input type="checkbox" data-matrix-col="{{ $key }}" />
                                            <span>{{ $label }}</span>
                                        </label>
                                    </th>
                                @endforeach
                            </tr>
                        </thead>

                        <tbody>
                            @foreach ($menus as $module)
                                <tr class="nv-matrix-group">
                                    <td colspan="{{ count($actions) + 1 }}">
                                        <label>
                                            <input type="checkbox" data-matrix-group="module-{{ $module->id }}" />
                                            <span>{{ $module->name }}</span>
                                        </label>
                                    </td>
                                </tr>

                                @foreach ($module->submodules as $submodule)
                                    <tr>
                                        <td>
                                            <div class="nv-matrix-module">
                                                <input type="checkbox" data-matrix-row
                                                       aria-label="Toggle all {{ $submodule->name }} permissions" />

                                                <span class="nv-matrix-icon">
                                                    <x-icon :name="$module->icon ?: 'grid'" />
                                                </span>

                                                <div>
                                                    <strong>{{ $submodule->name }}</strong>
                                                    <span class="nv-mono">{{ $submodule->url ?: '—' }}</span>
                                                </div>
                                            </div>
                                        </td>

                                        @foreach ($actions as $key => $label)
                                            <td class="is-center">
                                                <input
                                                    type="checkbox"
                                                    name="permissions[{{ $submodule->id }}][{{ $key }}]"
                                                    value="1"
                                                    data-matrix-cell
                                                    data-action="{{ $key }}"
                                                    data-group="module-{{ $module->id }}"
                                                    aria-label="{{ $submodule->name }} — {{ $label }}"
                                                    @checked(! empty($granted[$submodule->id][$key]))
                                                />
                                            </td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </x-card>
</div>

<div class="nv-actions" style="justify-content:flex-end;margin-top:22px">
    <a href="{{ route('users.index') }}" class="nv-btn nv-btn-outline">Cancel</a>
    <button type="submit" class="nv-btn nv-btn-primary">
        <x-icon name="check" /> {{ $user->exists ? 'Save changes' : 'Create user' }}
    </button>
</div>
