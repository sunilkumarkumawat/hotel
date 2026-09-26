@props([
    'name' => 'tax_choice',
    'choices' => null,
    'selected' => null,
    'branch' => null,
])

@php
    use App\Support\Tax;

    /*
        A Tax dropdown, and the one place in the app that draws one.

        It opens on "No Tax" because that is the first entry of every list Tax
        builds, and a screen that never touches it charges nothing — which is
        the rule the whole system is built on.

        Each option carries the percent it works out to, so the running total
        beside the form can follow the choice without asking the server. The
        figure that gets stored is still the server's: this is a preview, not
        an authority.
    */
    $branchId = $branch ?? \App\Helpers\Helper::getActiveBranchId();
    $choices ??= Tax::options($branchId);
    $selected = old($name, $selected ?? Tax::defaultChoice());
@endphp

<select name="{{ $name }}" id="{{ $name }}" {{ $attributes->class(['nv-select']) }}>
    @foreach ($choices as $key => $label)
        <option value="{{ $key }}"
                data-percent="{{ Tax::percent($key, $branchId) }}"
                @selected((string) $key === (string) $selected)>{{ $label }}</option>
    @endforeach
</select>
