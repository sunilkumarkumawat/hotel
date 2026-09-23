@extends('layouts.app')

@section('title', $outlet->exists ? 'Edit ' . $outlet->name : 'Create Outlet')

@php
    $action = $outlet->exists
        ? route('point-of-sale.setup.outlets.update', $outlet->id)
        : route('point-of-sale.setup.outlets.store');

    $chosen = old('users', $picked) ?: [];
@endphp

@section('content')
    <x-page-header
        :title="$outlet->exists ? 'Edit : ' . $outlet->name : 'Create Outlet'"
        :crumbs="['Home' => url('/'), 'Setup' => route('point-of-sale.setup'), 'Outlets' => route('point-of-sale.setup.outlets'), $outlet->exists ? 'Edit' : 'Create']"
    />

    @if ($errors->any())
        <div class="nv-mt">
            <x-alert tone="danger" title="Please fix {{ $errors->count() }} thing(s)">
                {{ $errors->first() }}
            </x-alert>
        </div>
    @endif

    @if ($outlet->trashed())
        <div class="nv-mt">
            <x-alert tone="warning" title="This outlet is deleted">
                It does not appear on any POS screen. Saving keeps it deleted — use Restore on the
                outlet list to bring it back.
            </x-alert>
        </div>
    @endif

    <form method="POST" action="{{ $action }}" enctype="multipart/form-data" class="nv-mt">
        @csrf
        @if ($outlet->exists)
            @method('PUT')
        @endif

        <div class="nv-grid nv-grid-3 nv-outlet-form">

            {{-- ── Column 1: who it is ───────────────────────────────────── --}}
            <div class="nv-stack">
                <x-card title="General Details">
                    <div class="nv-form-rows">
                        <x-field label="Status" name="status">
                            <x-select name="status" :options="[1 => 'Active', 0 => 'Inactive']"
                                      :selected="$outlet->status ?? 1" />
                        </x-field>

                        <x-field label="Name" name="name" required>
                            <x-input name="name" :value="$outlet->name" />
                        </x-field>

                        {{-- Three address lines under one label, the way the old
                             screen has it: the label belongs to the block, not to
                             each line, so lines two and three start in the input
                             column with nothing to their left. --}}
                        <x-field label="Address" name="address1">
                            <x-input name="address1" :value="$outlet->address1" />
                        </x-field>

                        <x-field name="address2" class="is-continued">
                            <x-input name="address2" :value="$outlet->address2" />
                        </x-field>

                        <x-field name="address3" class="is-continued">
                            <x-input name="address3" :value="$outlet->address3" />
                        </x-field>

                        <x-field label="Phone Number" name="phone1">
                            <x-input name="phone1" :value="$outlet->phone1" />
                        </x-field>

                        <x-field name="phone2" class="is-continued">
                            <x-input name="phone2" :value="$outlet->phone2" />
                        </x-field>

                        <x-field label="Website" name="website">
                            <x-input name="website" :value="$outlet->website" />
                        </x-field>

                        <x-field label="Email" name="email">
                            <x-input name="email" type="email" :value="$outlet->email" />
                        </x-field>

                        <x-field label="GST" name="gst_no">
                            <x-input name="gst_no" :value="$outlet->gst_no" />
                        </x-field>

                        <x-field label="CIN" name="cin_no">
                            <x-input name="cin_no" :value="$outlet->cin_no" />
                        </x-field>

                        <x-field label="PAN" name="pan_no">
                            <x-input name="pan_no" :value="$outlet->pan_no" />
                        </x-field>

                        <x-field label="SAC Code" name="sac_code">
                            <x-input name="sac_code" :value="$outlet->sac_code" />
                        </x-field>
                    </div>
                </x-card>

                <x-card title="Outlet Timing">
                    <div class="nv-form-rows">
                        <x-field label="Start Time" name="start_time">
                            <input type="time" name="start_time" id="start_time" class="nv-input"
                                   value="{{ old('start_time', $outlet->start_time ? date('H:i', strtotime($outlet->start_time)) : '') }}" />
                        </x-field>

                        <x-field label="End Time" name="end_time">
                            <input type="time" name="end_time" id="end_time" class="nv-input"
                                   value="{{ old('end_time', $outlet->end_time ? date('H:i', strtotime($outlet->end_time)) : '') }}" />
                        </x-field>
                    </div>
                </x-card>
            </div>

            {{-- ── Column 2: how it sells ────────────────────────────────── --}}
            <div class="nv-stack">
                <x-card title="Other Details">
                    <div class="nv-form-rows">
                        <x-field label="Bill Series" name="bill_series">
                            <x-input name="bill_series" :value="$outlet->bill_series" />
                        </x-field>

                        <x-field label="Bill Starting Number" name="bill_start_no">
                            <x-input name="bill_start_no" type="number" min="1"
                                     :value="$outlet->bill_start_no ?? 1" />
                        </x-field>

                        @foreach (\App\Models\Pos\Outlet::FLAGS as $flag => $meta)
                            {{-- An unticked box posts nothing; the hidden 0 sits
                                 earlier in the document, and the later value of
                                 the same name is the one that arrives. --}}
                            <input type="hidden" name="{{ $flag }}" value="0" />

                            {{-- The explanation is a tooltip rather than a line of
                                 its own: the old screen fits all thirteen switches
                                 on one screen, and thirteen sentences would not. --}}
                            <label @class(['nv-flag-row', 'is-key' => ($meta['group'] ?? null) === 'Order types'])
                                   for="flag_{{ $flag }}"
                                   @if (! empty($meta['help'])) title="{{ $meta['help'] }}" @endif>
                                <span class="nv-flag-box">
                                    <input type="checkbox" name="{{ $flag }}" id="flag_{{ $flag }}" value="1"
                                           @checked(old($flag, $outlet->{$flag})) />
                                </span>
                                <span class="nv-flag-text">{{ $meta['label'] }}</span>
                            </label>

                            @if ($flag === 'diff_liquor_series')
                                <x-field label="Liquor Bill Series" name="liquor_bill_series">
                                    <x-input name="liquor_bill_series" :value="$outlet->liquor_bill_series" />
                                </x-field>
                            @endif
                        @endforeach
                    </div>
                </x-card>

                <x-card title="Outlet Users">
                    <div class="nv-form-rows">
                        <x-field label="Users" name="users">
                            @if ($users->isEmpty())
                                <p class="nv-muted" style="margin:0">
                                    No users yet. Add them under Administration → Users.
                                </p>
                            @else
                                {{--
                                    Chips that are themselves the checkboxes: a
                                    filled chip is picked, an outlined one is not,
                                    and clicking flips it. No script involved, so
                                    the box cannot end up showing one thing while
                                    the form submits another.
                                --}}
                                <div class="nv-picks">
                                    @foreach ($users as $user)
                                        <label class="nv-pick" for="user_{{ $user->user_id }}">
                                            <input type="checkbox" name="users[]" id="user_{{ $user->user_id }}"
                                                   value="{{ $user->user_id }}"
                                                   @checked(in_array($user->user_id, $chosen, false)) />
                                            <span>{{ $user->name ?: $user->username }}</span>
                                        </label>
                                    @endforeach
                                </div>

                                <p class="nv-help">Tick nobody and everybody may bill through this till.</p>
                            @endif
                        </x-field>
                    </div>
                </x-card>
            </div>

            {{-- ── Column 3: how it prints ───────────────────────────────── --}}
            <div class="nv-stack">
                <x-card title="Print Details">
                    <div class="nv-form-rows">
                        <x-field label="Page Width" name="page_width">
                            <x-select name="page_width" :options="\App\Models\Pos\Outlet::PAGE_WIDTHS"
                                      :selected="$outlet->page_width ?? 80" />
                        </x-field>

                        <x-field label="Print Margin" name="print_margin">
                            <x-select name="print_margin" :options="\App\Models\Pos\Outlet::PRINT_MARGINS"
                                      :selected="$outlet->print_margin ?? 6" />
                        </x-field>

                        <x-field label="Header" name="print_header">
                            <x-input name="print_header" :value="$outlet->print_header" />
                        </x-field>

                        <x-field label="Tax Invoice Name" name="tax_invoice_name">
                            <x-input name="tax_invoice_name" :value="$outlet->tax_invoice_name" />
                        </x-field>

                        <x-field label="Header Font" name="header_font">
                            <x-select name="header_font" :options="\App\Models\Pos\Outlet::HEADER_FONTS"
                                      :selected="$outlet->header_font ?? 'Arial'" />
                        </x-field>

                        <x-field label="Header Font Size" name="header_font_size">
                            <div class="nv-input-group">
                                <x-input name="header_font_size" type="number" min="0" max="72"
                                         :value="$outlet->header_font_size ?? 0" />
                                <span class="nv-input-addon is-suffix">px</span>
                            </div>
                        </x-field>

                        <input type="hidden" name="header_font_bold" value="0" />
                        <label class="nv-flag-row" for="header_font_bold">
                            <span class="nv-flag-box">
                                <input type="checkbox" name="header_font_bold" id="header_font_bold" value="1"
                                       @checked(old('header_font_bold', $outlet->header_font_bold ?? true)) />
                            </span>
                            <span class="nv-flag-text">Header Font Bold</span>
                        </label>

                        <x-field label="Footer" name="print_footer">
                            <x-input name="print_footer" :value="$outlet->print_footer" />
                        </x-field>

                        <input type="hidden" name="guest_signature_print" value="0" />
                        <label class="nv-flag-row" for="guest_signature_print"
                               title="Leaves a signing line at the bottom — needed to post a bill to a room.">
                            <span class="nv-flag-box">
                                <input type="checkbox" name="guest_signature_print" id="guest_signature_print" value="1"
                                       @checked(old('guest_signature_print', $outlet->guest_signature_print ?? true)) />
                            </span>
                            <span class="nv-flag-text">Guest Signature Print</span>
                        </label>

                        {{-- Everything about the logo is wrapped in one element:
                             a row of this grid holds a label and a control, and
                             four loose children would wrap back under the label
                             column instead of stacking. --}}
                        <x-field label="Logo" name="logo" class="is-top">
                            <div class="nv-logo-stack">
                                <div class="nv-logo-box">
                                    @if ($outlet->logoUrl())
                                        <img src="{{ $outlet->logoUrl() }}" alt="{{ $outlet->name }} logo" />
                                    @else
                                        <span class="nv-muted"><x-icon name="package" /></span>
                                    @endif
                                </div>

                                <input type="file" name="logo" id="logo" class="nv-input nv-logo-file"
                                       accept="image/png,image/jpeg" />

                                @if ($outlet->logo_path)
                                    <input type="hidden" name="remove_logo" value="0" />
                                    <label class="nv-pick is-danger" for="remove_logo">
                                        <input type="checkbox" name="remove_logo" id="remove_logo" value="1" />
                                        <span><x-icon name="trash" /> Remove Logo</span>
                                    </label>
                                @endif
                            </div>

                            <p class="nv-help">PNG or JPG, up to 1 MB — prints at the top of the receipt.</p>
                        </x-field>
                    </div>

                    @if ($outlet->logo_path && ! $outlet->logoUrl())
                        <x-alert tone="warning" title="Logo file missing">
                            A logo was uploaded but the file is not on disk. Run
                            <code>php artisan storage:link</code> once, or upload it again.
                        </x-alert>
                    @endif
                </x-card>
            </div>
        </div>

        <div class="nv-form-foot">
            <button type="submit" class="nv-btn nv-btn-primary">
                <x-icon name="check" /> Save
            </button>

            <a href="{{ route('point-of-sale.setup.outlets') }}" class="nv-btn nv-btn-outline">Back To List</a>
        </div>
    </form>
@endsection
