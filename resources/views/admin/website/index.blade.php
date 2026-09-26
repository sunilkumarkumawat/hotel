@extends('layouts.app')

@section('title', 'Website Content')

@section('content')
    <x-page-header
        title="Website Content"
        subtitle="What guests see on your public booking website — rooms, photos, amenities and rates."
        :crumbs="['Home' => url('/'), 'Administration', 'Website Content']"
    >
        <x-slot:actions>
            <a href="{{ route('site.hotels') }}" target="_blank" rel="noopener" class="nv-btn nv-btn-outline">
                <x-icon name="globe" /> View Public Site
            </a>
        </x-slot:actions>
    </x-page-header>

    @if ($branches->isEmpty())
        <div class="nv-mt">
            <x-alert tone="info" title="No branches yet">Add a branch first, then come back here to build its website.</x-alert>
        </div>
    @else
        <div class="nv-grid nv-grid-2 nv-mt">
            @foreach ($branches as $branch)
                @php $site = $branch->website; @endphp
                <x-card :title="$branch->branch_name" :subtitle="collect([$branch->city?->name, $branch->state?->name])->filter()->implode(', ') ?: null">
                    <x-slot:actions>
                        @if ($site)
                            <x-badge :tone="$site->is_published ? 'success' : 'warning'">{{ $site->is_published ? 'Live' : 'Hidden' }}</x-badge>
                        @else
                            <x-badge tone="info">Not set up</x-badge>
                        @endif
                    </x-slot:actions>

                    @if ($site)
                        <p class="nv-help" style="margin-top:0;">
                            {{ $site->tagline ?: 'No tagline written yet.' }}
                        </p>
                        <p class="nv-sub">/hotels/{{ $site->slug }}</p>
                    @else
                        <p class="nv-help" style="margin-top:0;">This hotel has no website content yet — rooms and photos won't show online until you add some.</p>
                    @endif

                    <div class="nv-actions" style="margin-top:16px;">
                        <a href="{{ route('admin.website.edit', $branch) }}" class="nv-btn nv-btn-primary">
                            <x-icon name="pencil" /> {{ $site ? 'Edit Website' : 'Set Up Website' }}
                        </a>
                        @if ($site && $site->is_published)
                            <a href="{{ route('site.home', $site->slug) }}" target="_blank" rel="noopener" class="nv-btn nv-btn-outline">
                                <x-icon name="external" /> View Live
                            </a>
                        @endif
                    </div>
                </x-card>
            @endforeach
        </div>
    @endif
@endsection
