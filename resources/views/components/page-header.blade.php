@props(['title', 'subtitle' => null, 'crumbs' => []])

<div class="nv-page-head">
    <div>
        @if ($crumbs)
            <nav class="nv-crumbs">
                @foreach ($crumbs as $label => $url)
                    @if (! $loop->first)
                        <x-icon name="chevron-right" />
                    @endif

                    @if (is_int($label))
                        <span>{{ $url }}</span>
                    @else
                        <a href="{{ $url }}">{{ $label }}</a>
                    @endif
                @endforeach
            </nav>
        @endif

        <h1 class="nv-title">{{ $title }}</h1>

        @if ($subtitle)
            <p class="nv-subtitle">{{ $subtitle }}</p>
        @endif
    </div>

    @isset($actions)
        <div class="nv-actions">{{ $actions }}</div>
    @endisset
</div>
