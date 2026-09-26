@extends('layouts.app')

@section('title', 'Edit Website · ' . $branch->branch_name)

@section('content')
    <x-page-header
        :title="'Edit Website — ' . $branch->branch_name"
        :subtitle="'Public page: /hotels/' . $site->slug"
        :crumbs="['Home' => url('/'), 'Administration' => route('admin.website.index'), 'Website Content' => route('admin.website.index'), $branch->branch_name]"
    >
        <x-slot:actions>
            @if ($site->is_published)
                <a href="{{ route('site.home', $site->slug) }}" target="_blank" rel="noopener" class="nv-btn nv-btn-outline">
                    <x-icon name="external" /> View Live
                </a>
            @endif
            <a href="{{ route('admin.website.index') }}" class="nv-btn nv-btn-outline">
                <x-icon name="chevron-left" /> Back to List
            </a>
        </x-slot:actions>
    </x-page-header>

    @if ($errors->any())
        <div class="nv-mt">
            <x-alert tone="danger" title="Please fix {{ $errors->count() }} thing(s)">{{ $errors->first() }}</x-alert>
        </div>
    @endif

    {{-- ══ Hotel details ═══════════════════════════════════════════════════ --}}
    <div class="nv-mt">
        <x-card title="Hotel Details" subtitle="The story, amenities and policies guests see on your hotel's page">
            <form method="POST" action="{{ route('admin.website.update', $branch) }}" enctype="multipart/form-data">
                @csrf
                @method('PUT')

                <div class="nv-grid nv-grid-2">
                    <x-field label="Website Address (slug)" name="slug" required
                              help="Your page will be at /hotels/this-text — letters, numbers and dashes only.">
                        <x-input name="slug" value="{{ $site->slug }}" maxlength="80" />
                    </x-field>

                    <x-field label="Tagline" name="tagline" help="One short line under your hotel's name.">
                        <x-input name="tagline" value="{{ $site->tagline }}" maxlength="255" placeholder="A boutique stay in the heart of the Pink City" />
                    </x-field>
                </div>

                <x-field label="About This Hotel" name="about" help="A few paragraphs about your property. Shown on the home page.">
                    <x-textarea name="about" value="{{ $site->about }}" rows="5" />
                </x-field>

                <div class="nv-grid nv-grid-2">
                    <x-field label="Amenities" name="amenities" help="One per line — e.g. Free WiFi, Swimming Pool, Airport Pickup.">
                        <x-textarea name="amenities" value="{{ implode(chr(10), $site->amenityLabels()) }}" rows="6" placeholder="Free WiFi&#10;Swimming Pool&#10;Airport Pickup" />
                    </x-field>

                    <x-field label="Current Offers" name="offers" help="One per line — shown as highlight cards. Leave blank if none right now.">
                        <x-textarea name="offers" value="{{ implode(chr(10), $site->offers ?? []) }}" rows="6" placeholder="Stay 3 nights, pay for 2&#10;Free breakfast this month" />
                    </x-field>
                </div>

                <x-field label="Policies" name="policies" help="Cancellation, ID proof, pets, etc. Shown to guests before they book.">
                    <x-textarea name="policies" value="{{ $site->policies }}" rows="4" />
                </x-field>

                <div class="nv-grid nv-grid-3">
                    <x-field label="Check-in Time" name="checkin_time">
                        <x-input name="checkin_time" value="{{ $site->checkin_time }}" maxlength="20" placeholder="12:00 PM" />
                    </x-field>

                    <x-field label="Check-out Time" name="checkout_time">
                        <x-input name="checkout_time" value="{{ $site->checkout_time }}" maxlength="20" placeholder="11:00 AM" />
                    </x-field>

                    <x-field label="Hero Photo" name="hero_image" help="Shown behind your hotel name on the home page.">
                        @if ($site->hero_image)
                            <img src="{{ asset($site->hero_image) }}" alt="" style="width:100%;max-width:160px;border-radius:10px;margin-bottom:8px;display:block;" />
                        @endif
                        <input type="file" name="hero_image" class="nv-input" accept="image/*" />
                    </x-field>
                </div>

                <div class="nv-mt">
                    <x-toggle title="Publish this website" name="is_published" :checked="$site->is_published"
                              description="When off, /hotels/{{ $site->slug }} and this hotel's entry on the hotel picker are both hidden from guests." />
                </div>

                <div class="nv-actions nv-mt" style="justify-content:flex-end">
                    <button type="submit" class="nv-btn nv-btn-primary"><x-icon name="check" /> Save Hotel Details</button>
                </div>
            </form>
        </x-card>
    </div>

    {{-- ══ Rooms & photos ══════════════════════════════════════════════════ --}}
    <div class="nv-mt">
        <h3 class="nv-title" style="font-size:18px;">Rooms &amp; Photos</h3>
        <p class="nv-help" style="margin-top:2px;">Every active room type below is what guests can actually book online — rates come straight from each room type's own rent.</p>
    </div>

    @if ($roomTypes->isEmpty())
        <div class="nv-mt">
            <x-alert tone="info" title="No active room types">Add or activate room types in Masters → Room Types, then come back here to add photos and descriptions.</x-alert>
        </div>
    @else
        @foreach ($roomTypes as $roomType)
            <div class="nv-mt">
                <x-card :title="$roomType->name" :subtitle="'₹' . number_format($roomType->base_rent, 0) . ' / night · ' . $roomType->max_adult . ' adults · ' . $roomType->max_child . ' child'">
                    @if ($roomType->images->isNotEmpty())
                        <div style="display:grid; grid-template-columns:repeat(auto-fill, minmax(120px, 1fr)); gap:12px; margin-bottom:18px;">
                            @foreach ($roomType->images as $image)
                                <div style="border:1px solid var(--nv-border, #e4dde3); border-radius:10px; overflow:hidden;">
                                    <img src="{{ $image->url }}" alt="" style="width:100%; height:90px; object-fit:cover; display:block;" />
                                    <div style="display:flex; align-items:center; justify-content:space-between; gap:4px; padding:6px 8px;">
                                        @if ($image->is_cover)
                                            <x-badge tone="success" plain>Cover</x-badge>
                                        @else
                                            <form method="POST" action="{{ route('admin.website.room-type-image.cover', $image) }}">
                                                @csrf
                                                @method('PUT')
                                                <button type="submit" class="nv-btn nv-btn-outline" style="padding:2px 8px; font-size:12px;" title="Make cover photo">
                                                    <x-icon name="star" size="14" />
                                                </button>
                                            </form>
                                        @endif

                                        <form method="POST" action="{{ route('admin.website.room-type-image.destroy', $image) }}"
                                              data-confirm="Delete this photo?" data-confirm-title="Delete photo" data-confirm-action="Delete">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="nv-btn nv-btn-outline" style="padding:2px 8px; font-size:12px;" title="Delete photo">
                                                <x-icon name="trash" size="14" />
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <p class="nv-help" style="margin-top:0;">No photos yet — this room will show a plain placeholder on the website until you add some.</p>
                    @endif

                    <form method="POST" action="{{ route('admin.website.room-type.update', $roomType) }}" enctype="multipart/form-data">
                        @csrf
                        @method('PUT')

                        <x-field label="Add Photos" :name="'images_' . $roomType->id" help="Choose one or more photos to add. The first photo ever added becomes the cover automatically.">
                            <input type="file" name="images[]" id="images_{{ $roomType->id }}" class="nv-input" accept="image/*" multiple />
                        </x-field>

                        <x-field label="Description" :name="'description_' . $roomType->id">
                            <textarea name="description" id="description_{{ $roomType->id }}" class="nv-textarea" rows="3">{{ old('description', $roomType->website?->description) }}</textarea>
                        </x-field>

                        <x-field label="Highlights" :name="'highlights_' . $roomType->id" help="One per line — e.g. City View, King Bed, Balcony.">
                            <textarea name="highlights" id="highlights_{{ $roomType->id }}" class="nv-textarea" rows="3">{{ old('highlights', implode("\n", $roomType->website?->highlights ?? [])) }}</textarea>
                        </x-field>

                        <div class="nv-actions" style="justify-content:flex-end">
                            <button type="submit" class="nv-btn nv-btn-primary"><x-icon name="check" /> Save {{ $roomType->name }}</button>
                        </div>
                    </form>
                </x-card>
            </div>
        @endforeach
    @endif
@endsection
