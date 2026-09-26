@if ($errors->any())
    <div style="margin-bottom:18px">
        <x-alert tone="danger" title="Please fix {{ $errors->count() }} field(s)">{{ $errors->first() }}</x-alert>
    </div>
@endif

<div class="nv-grid nv-grid-form">
    <div class="nv-form-aside">
        <h3>Branch</h3>
        <p>Each user belongs to a branch, and their permissions are saved per branch.</p>
    </div>

    <x-card>
        <div class="nv-form-grid">
            <x-field label="Branch code" name="branch_code" required help="Short and unique — HO, JP1, MUM.">
                <x-input name="branch_code" :value="$branch->branch_code" placeholder="HO" />
            </x-field>

            <x-field label="Branch name" name="branch_name" required>
                <x-input name="branch_name" :value="$branch->branch_name" placeholder="Head Office" />
            </x-field>

            <x-field label="Director / administrator" name="director_administrator">
                <x-input name="director_administrator" :value="$branch->director_administrator" />
            </x-field>

            <x-field label="Business type" name="business_type">
                <x-input name="business_type" :value="$branch->business_type" placeholder="Hotel" />
            </x-field>

            <x-field label="Mobile number" name="mobile_number">
                <x-input name="mobile_number" :value="$branch->mobile_number" placeholder="98765 43210" />
            </x-field>

            <x-field label="Email" name="email">
                <x-input name="email" type="email" :value="$branch->email" placeholder="branch@example.com" />
            </x-field>

            <x-field label="Address" name="address" wide>
                <x-input name="address" :value="$branch->address" />
            </x-field>
        </div>
    </x-card>
</div>

<hr class="nv-hr" />

<div class="nv-grid nv-grid-form">
    <div class="nv-form-aside">
        <h3>On printed documents</h3>
        <p>
            These go on the letterhead of the Guest Registration Card and every
            bill after it. Fill them in before you print anything for a guest.
        </p>
    </div>

    <x-card>
        <div class="nv-form-grid">
            <x-field label="Legal name" name="legal_name"
                     help="The name on the GST certificate, if it is not the name over the door.">
                <x-input name="legal_name" :value="$branch->legal_name"
                         placeholder="Krishna Hospitality Pvt Ltd" />
            </x-field>

            <x-field label="GSTIN" name="gst_no" help="15 characters, as registered.">
                <x-input name="gst_no" :value="$branch->gst_no" maxlength="15"
                         style="text-transform:uppercase" placeholder="08ABCDE1234F1Z5" />
            </x-field>

            <x-field label="SAC code" name="sac_code" help="996311 for hotel accommodation.">
                <x-input name="sac_code" :value="$branch->sac_code" placeholder="996311" />
            </x-field>

            <x-field label="Registration card terms" name="reg_card_terms" wide
                     help="Printed under Terms & Condition on the card. One line each.">
                <x-textarea name="reg_card_terms" rows="4"
                            placeholder="Check out time is 11:00 AM.&#10;The hotel is not responsible for valuables left in the room.">{{ $branch->reg_card_terms }}</x-textarea>
            </x-field>
        </div>
    </x-card>
</div>

<hr class="nv-hr" />

<div class="nv-grid nv-grid-form">
    <div class="nv-form-aside">
        <h3>Location</h3>
        <p>Pick a country to load its states, then a state to load its cities.</p>
    </div>

    <x-card>
        <div class="nv-form-grid">
            <x-field label="Country" name="country_id" required>
                <x-select name="country_id" :options="$countries->pluck('name', 'id')->all()"
                          :selected="$branch->country_id" placeholder="Choose a country…"
                          data-cascade="state" data-cascade-url="{{ url('get-state-id') }}" />
            </x-field>

            <x-field label="State" name="state_id" required>
                <x-select name="state_id" :options="$states->pluck('name', 'id')->all()"
                          :selected="$branch->state_id" placeholder="Choose a state…"
                          data-cascade="city" data-cascade-url="{{ url('get-city-id') }}" data-cascade-target="state" />
            </x-field>

            <x-field label="City" name="city_id" required>
                <x-select name="city_id" :options="$cities->pluck('name', 'id')->all()"
                          :selected="$branch->city_id" placeholder="Choose a city…" data-cascade-target="city" />
            </x-field>

            <x-field label="Pin code" name="pin_code">
                <x-input name="pin_code" :value="$branch->pin_code" placeholder="302001" />
            </x-field>
        </div>

        <label class="nv-check">
            <input type="checkbox" name="status" value="1" @checked(old('status', $branch->exists ? $branch->status : 1)) />
            <span>Branch is active — inactive branches cannot be selected</span>
        </label>
    </x-card>
</div>

<div class="nv-actions" style="justify-content:flex-end;margin-top:22px">
    <a href="{{ route('viewBranch.index') }}" class="nv-btn nv-btn-outline">Cancel</a>
    <button type="submit" class="nv-btn nv-btn-primary">
        <x-icon name="check" /> {{ $branch->exists ? 'Save changes' : 'Create branch' }}
    </button>
</div>
