@props(['title' => null, 'subtitle' => null, 'flush' => false])

<div {{ $attributes->class(['nv-card']) }}>
    @if ($title || isset($actions))
        <div class="nv-card-head">
            <div>
                @if ($title)
                    <h3 class="nv-card-title">{{ $title }}</h3>
                @endif
                @if ($subtitle)
                    <p class="nv-card-sub">{{ $subtitle }}</p>
                @endif
            </div>

            @isset($actions)
                <div class="nv-actions">{{ $actions }}</div>
            @endisset
        </div>
    @endif

    <div @class(['nv-card-body', 'is-flush' => $flush])>
        {{ $slot }}
    </div>

    @isset($footer)
        <div class="nv-card-foot">{{ $footer }}</div>
    @endisset
</div>
