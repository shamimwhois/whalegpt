{{--
    One row inside a panel.

        <x-ui.menu-item icon="pin" x-on:click="togglePinned(chat)">
            {{ chat.title }}
        </x-ui.menu-item>

        <x-ui.menu-item
            active="mode === option.id"
            x-on:click="mode = option.id; modeOpen = false"
        >
            <span class="block truncate" x-text="option.label"></span>
            <span class="mt-0.5 block text-[11px] leading-snug text-muted" x-text="option.description"></span>
        </x-ui.menu-item>

    The dropdowns came in two shapes: a single line, and a line with a muted
    explanation under it. The caller owns that content — it usually comes out of
    an Alpine object, which Blade cannot reach — so this supplies the row chrome
    and the caller fills in the slot.

    `active` is an Alpine expression string, not a Blade binding: writing
    `:active="mode === option.id"` would make Blade evaluate that as PHP and
    fatal on every render. Same reason there is no `shortcut` prop — the trailing
    hint is a `suffix` slot, because its value is nearly always dynamic.
--}}
@props([
    'icon' => null,
    'active' => null,
    'danger' => false,
])

<button
    type="button"
    role="{{ $active ? 'menuitemradio' : 'menuitem' }}"
    @if ($active)
        x-bind:aria-checked="{{ $active }} ? 'true' : 'false'"
    @endif
    {{ $attributes->merge([
        'class' => 'whale-menu-item items-start '.($danger ? 'whale-menu-item-danger' : ''),
    ]) }}
>
    @if ($icon)
        <x-ui.icon :name="$icon" class="mt-0.5 h-4 w-4 shrink-0 text-muted" />
    @endif

    <span class="min-w-0 flex-1">
        {{ $slot }}
    </span>

    @if (isset($suffix) || $active)
        <span class="mt-0.5 flex shrink-0 items-center gap-1.5">
            {{ $suffix ?? '' }}

            @if ($active)
                <x-ui.icon x-show="{{ $active }}" name="check" class="h-3.5 w-3.5 text-accent" />
            @endif
        </span>
    @endif
</button>