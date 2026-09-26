@php /** @var \App\Models\Common\SubModule|null $submodule */ @endphp

<div class="nv-sub-fields">
    <x-field label="Name" name="name" required>
        <x-input name="name" :value="$submodule?->name" placeholder="Room List" />
    </x-field>

    <x-field label="URL" name="url" required help="Route name or path — also the permission key.">
        <x-input name="url" :value="$submodule?->url" placeholder="room-list" />
    </x-field>

    <x-field label="Sort" name="sort">
        <x-input name="sort" type="number" min="0" max="127" :value="$submodule?->sort" />
    </x-field>
</div>
