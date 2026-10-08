{{--
    Command palette (Ctrl/Cmd+K).

    Follows the workspace IDE's palette deliberately: one query, one highlighted
    row, arrows to move, Enter to run, Escape to dismiss. Two palettes with
    different interaction models is worse than one that is slightly limited.

    Commands live in `commandList()` as data with a `run` on each, so adding one
    is a single object rather than a new markup block and a new branch in a
    keyboard handler.

    Focus is moved to the input on open, and the input keeps it for the palette's
    whole life so the arrows and Enter reach it. The list rows are buttons, so
    Tab is held inside the dialog as well — `aria-modal` claims the background is
    inert, and this is what makes that true for a keyboard.
--}}
<div
    x-show="palette.open"
    x-init="$watch('palette.open', (open) => open ? trapFocusIn($el) : releaseFocus())"
    x-on:keydown.tab="keepFocusInside($event)"
    x-transition:enter="transition ease-out duration-100"
    x-transition:enter-start="opacity-0"
    x-transition:enter-end="opacity-100"
    class="fixed inset-0 z-[60] flex justify-center px-4 pt-[12vh]"
    style="display:none"
    x-on:click.self="closePalette()"
    role="dialog"
    aria-modal="true"
    aria-label="Command palette"
>
    <div class="absolute inset-0 bg-black/40 backdrop-blur-[2px]" x-on:click="closePalette()"></div>

    <div class="whale-pop relative flex w-full max-w-xl flex-col overflow-hidden p-0 shadow-2xl">
        <label for="whale-palette-input" class="sr-only">Search commands and chats</label>
        <input
            id="whale-palette-input"
            x-ref="paletteInput"
            type="text"
            role="combobox"
            aria-expanded="true"
            aria-controls="whale-palette-list"
            :aria-activedescendant="paletteItems().length > 0
                ? 'whale-palette-item-' + palette.index
                : null"
            x-model="palette.query"
            x-on:input="palette.index = 0"
            x-on:keydown.down.prevent="movePalette(1)"
            x-on:keydown.up.prevent="movePalette(-1)"
            x-on:keydown.enter.prevent="runPalette()"
            x-on:keydown.escape.prevent="closePalette()"
            placeholder="Search chats and commands…"
            autocomplete="off"
            class="w-full border-b border-line bg-transparent px-4 py-3 text-sm
                   placeholder:text-muted focus:outline-none"
        >

        <div id="whale-palette-list" role="listbox" class="whale-scroll max-h-80 overflow-y-auto p-1">
            <template x-for="(item, index) in paletteItems()" :key="item.id">
                <button
                    :id="'whale-palette-item-' + index"
                    type="button"
                    role="option"
                    :aria-selected="palette.index === index ? 'true' : 'false'"
                    x-on:click="runPaletteItem(item)"
                    x-on:mouseenter="palette.index = index"
                    class="flex w-full items-center gap-2.5 rounded-lg px-3 py-2 text-left text-sm transition"
                    :class="palette.index === index ? 'bg-wash-strong text-ink' : 'text-body hover:bg-wash'"
                >
                    {{-- Two icons rather than one bound name: the icon's `name` prop is resolved
                     by Blade at render time, so `:name="item.kind ..."` would
                     make Blade evaluate that expression as PHP and fatal. `x-show`
                     is the honest way to swap a value that only exists at runtime. --}}
                    <x-ui.icon
                        name="rows"
                        x-show="item.kind === 'chat'"
                        class="h-4 w-4 shrink-0 text-muted"
                    />
                    <x-ui.icon
                        name="bolt"
                        x-show="item.kind !== 'chat'"
                        class="h-4 w-4 shrink-0 text-accent"
                    />

                    <span class="min-w-0 flex-1 truncate" x-text="item.label"></span>

                    <span
                        x-show="item.hint"
                        class="shrink-0 text-[11px] text-muted"
                        x-text="item.hint"
                    ></span>
                </button>
            </template>

            <p x-show="paletteItems().length === 0" class="px-3 py-3 text-xs text-muted">
                Nothing matches.
            </p>
        </div>
    </div>
</div>
