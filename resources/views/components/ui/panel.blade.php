{{--
    The floating surface every dropdown shares.

    Six popovers across the chat views each re-declared the same shell: an
    absolutely positioned panel, a border, a radius, a shadow, a transition and
    a click-outside handler. They drifted, and the next menu would drift again.

        <x-ui.panel show="modeOpen">
            …
        </x-ui.panel>

        <x-ui.panel show="menuFor === chat.id" hide="menuFor = null">
            …
        </x-ui.panel>

    `show` and `hide` are Alpine expression *strings*, not Blade bindings. That
    distinction is deliberate and load-bearing: writing `:show="modeOpen"`
    instead would have Blade evaluate `modeOpen` as a PHP constant and fatal on
    every render. Props carry static config, `$attributes` carries behaviour.

    The inline `display:none` is deliberate too — it stops the panel painting
    before Alpine has booted and taken x-show over.
--}}
@props([
    'show',
    'hide' => null,
    'align' => 'left',
    'side' => 'bottom',
    'width' => 'w-56',
    'label' => 'Menu',
])

@php
    // Which corner the panel grows from. Keeping this a closed set is what
    // stops a popover opening off the edge of its own trigger.
    $origin = $align === 'right' ? 'right-0 origin-top-right' : 'left-0 origin-top-left';

    // A panel anchored to the bottom of the screen has to open upward, or it
    // grows straight off the edge.
    $placement = $side === 'top' ? "bottom-full {$origin} mb-2" : "top-full {$origin} mt-2";

    // Defaults to the obvious counterpart of `show`, which is right for a plain
    // flag and overridden for an expression like a menu's `menuFor === id`.
    $dismiss = $hide ?? "{$show} = false";
@endphp

<div
    x-show="{{ $show }}"
    x-on:click.outside="{{ $dismiss }}"
    x-on:keydown.escape="{{ $dismiss }}"
    role="menu"
    aria-label="{{ $label }}"
    style="display:none"
    x-transition:enter="transition ease-out duration-150"
    x-transition:enter-start="opacity-0 -translate-y-1"
    x-transition:enter-end="opacity-100 translate-y-0"
    x-transition:leave="transition ease-in duration-100"
    x-transition:leave-start="opacity-100"
    x-transition:leave-end="opacity-0"
    {{ $attributes->merge([
        'class' => "whale-pop absolute z-40 max-w-[min(22rem,calc(100vw-2rem))] {$placement} {$width}",
    ]) }}
>
    {{ $slot }}
</div>