@props([
    'label' => null,
    'name' => null,
    'help' => null,
    'required' => false,
    'wide' => false,
    // When the control's id is not its name — two dialogs on one page cannot
    // both call their date field `from_date`, but both still report errors
    // against that one name.
    'for' => null,
])

<div {{ $attributes->class(['nv-field', 'nv-span-2' => $wide]) }}>
    @if ($label)
        <label class="nv-label" @if ($for ?? $name) for="{{ $for ?? $name }}" @endif>
            {{ $label }}
            @if ($required)
                <span class="nv-req">*</span>
            @endif
        </label>
    @endif

    {{ $slot }}

    @if ($help)
        <p class="nv-help">{{ $help }}</p>
    @endif

    @if ($name)
        @error($name)
            <p class="nv-error">{{ $message }}</p>
        @enderror
    @endif
</div>
