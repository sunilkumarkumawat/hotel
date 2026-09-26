@props(['title', 'description' => null, 'name' => null, 'checked' => false])

<div class="nv-switch">
    <div class="nv-switch-text">
        <strong>{{ $title }}</strong>
        @if ($description)
            <span>{{ $description }}</span>
        @endif
    </div>

    <label class="nv-toggle">
        <input type="checkbox" @if ($name) name="{{ $name }}" @endif value="1" @checked($checked) />
        <span></span>
    </label>
</div>
