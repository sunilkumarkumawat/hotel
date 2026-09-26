{{-- Shared site footer — expects $site (BranchWebsite, with branch loaded) in scope. --}}
@php
    $branch = $site->branch;
    $addressLine = collect([$branch->address, $branch->city?->name, $branch->state?->name, $branch->pin_code])
        ->filter()
        ->implode(', ');
@endphp
<footer class="site-footer" id="contact">
    <div class="site-wrap">
        <div class="site-footer-grid">
            <div>
                <h4>{{ $branch->branch_name }}</h4>
                <p style="max-width: 40ch; margin: 10px 0 0; font-size: 14px; line-height: 1.7;">
                    {{ $site->tagline ?: 'A place run for the traveller who came, and the one about to.' }}
                </p>
            </div>

            <div>
                <h4>Get in touch</h4>
                <p style="margin: 10px 0 0; font-size: 14px; line-height: 1.9;">
                    @if ($addressLine)
                        {{ $addressLine }}<br />
                    @endif
                    @if ($branch->mobile_number)
                        <a href="tel:{{ $branch->mobile_number }}">{{ $branch->mobile_number }}</a><br />
                    @endif
                    @if ($branch->email)
                        <a href="mailto:{{ $branch->email }}">{{ $branch->email }}</a>
                    @endif
                </p>
            </div>

            <div>
                <h4>Stay times</h4>
                <p style="margin: 10px 0 0; font-size: 14px; line-height: 1.9;">
                    Check-in from {{ $site->checkin_time ?: '12:00 PM' }}<br />
                    Check-out by {{ $site->checkout_time ?: '11:00 AM' }}
                </p>
                <a href="{{ route('site.rooms', $site->slug) }}" class="site-btn site-btn-ghost site-btn-sm" style="margin-top: 14px;">Check availability</a>
            </div>
        </div>

        <div class="site-footer-bottom">
            <span>&copy; {{ now()->year }} {{ $branch->branch_name }}. All rights reserved.</span>
            <span><a href="{{ route('site.hotels') }}">Our Hotels</a></span>
        </div>
    </div>
</footer>
