{{-- Model picker: only providers and models this app can actually run. --}}
<div class="relative min-w-0 flex-1">
    <button
        type="button"
        x-on:click="pickerOpen = !pickerOpen"
        x-on:click.outside="pickerOpen = false"
        x-on:keydown.escape="pickerOpen = false"
        :aria-expanded="pickerOpen ? 'true' : 'false'"
        aria-haspopup="menu"
        class="flex max-w-full items-center gap-1.5 rounded-lg px-2 py-1.5 text-sm font-medium
               transition hover:bg-wash focus-visible:outline-2 focus-visible:-outline-offset-2
               focus-visible:outline-accent"
    >
        <span class="truncate" x-text="modelLabel()">Loading…</span>

        {{-- The active type filter, shown next to the model so the narrowed list is never a surprise. --}}
        <span
            x-show="modelFilter !== 'all'"
            class="hidden shrink-0 rounded-full bg-wash px-1.5 py-0.5 text-[10px] font-medium
                   text-body sm:inline"
            style="display:none"
            x-text="modelFilterOptions().find((option) => option.id === modelFilter)?.label"
        ></span>

        <x-ui.icon name="chevron-down" class="h-3.5 w-3.5 shrink-0 opacity-60" />
    </button>

    <x-ui.panel show="pickerOpen" hide="pickerOpen = false" width="w-72" label="Choose a model">
        {{-- Type filter: narrows the list to models of a kind, so a reasoning
             model never has to be hunted past the fast ones. --}}
        <div class="flex gap-1 border-b border-line p-1.5">
            <template x-for="option in modelFilterOptions()" :key="option.id">
                <button
                    type="button"
                    x-on:click="modelFilter = option.id"
                    class="rounded-md px-2 py-1 text-[11px] font-medium transition
                           focus-visible:outline-2 focus-visible:outline-accent"
                    :class="modelFilter === option.id
                        ? 'bg-wash-strong text-ink'
                        : 'text-muted hover:bg-wash'"
                    x-text="option.label"
                ></button>
            </template>

            <span class="ml-auto self-center pr-1 text-[10px] tabular-nums text-muted">
                <span x-text="visibleModelCount()"></span>
            </span>
        </div>

        <div class="whale-scroll max-h-80 overflow-y-auto p-1.5">
            <p
                x-show="visibleModelCount() === 0"
                class="px-2.5 py-3 text-[12px] leading-snug text-muted"
                style="display:none"
            >
                No configured model of that type. Set the provider's API key in
                Provider settings, or switch the filter back to All.
            </p>

            <template x-for="provider in providers" :key="provider.name">
                <div class="mb-1 last:mb-0" x-show="providerVisible(provider)">
                    <div class="flex items-center gap-2 px-2.5 py-1.5">
                        <span
                            class="h-1.5 w-1.5 shrink-0 rounded-full"
                            :class="provider.configured ? 'bg-accent' : 'bg-faint'"
                        ></span>
                        <span
                            class="truncate text-xs font-semibold"
                            :class="provider.configured ? 'text-ink' : 'text-muted'"
                            x-text="provider.label"
                        ></span>
                        <span
                            x-show="provider.default"
                            class="shrink-0 rounded-full bg-wash px-2 py-0.5 text-[10px]
                                   font-medium text-body"
                        >default</span>
                    </div>

                    <template x-for="model in provider.models" :key="provider.name + model.id">
                        <button
                            type="button"
                            x-show="modelVisible(model)"
                            x-on:click="selectModel(provider.name, model.id); pickerOpen = false"
                            class="flex w-full items-center gap-2 rounded-lg px-2.5 py-2 text-left
                                   text-sm text-body transition hover:bg-wash
                                   focus-visible:outline-2 focus-visible:outline-accent"
                        >
                            <span class="min-w-0 flex-1 truncate" x-text="model.label"></span>
                            <x-ui.icon
                                name="check"
                                x-show="provider.name === providerName && model.id === modelName"
                                class="h-4 w-4 shrink-0 text-accent"
                            />
                        </button>
                    </template>

                    <p
                        x-show="!provider.configured && modelFilter === 'all'"
                        class="px-2.5 pb-1.5 text-[11px] leading-snug text-muted"
                    >
                        Set <span class="font-mono" x-text="provider.environment"></span> to use this provider.
                    </p>
                </div>
            </template>
        </div>

        <div class="border-t border-line p-1.5">
            <button
                type="button"
                x-on:click="pickerOpen = false; settingsOpen = true; loadLocalModels()"
                class="flex w-full items-center gap-2 rounded-lg px-2.5 py-2 text-left text-sm
                       text-body transition hover:bg-wash focus-visible:outline-2
                       focus-visible:outline-accent"
            >
                <x-ui.icon name="sliders" class="h-4 w-4 shrink-0 text-muted" />
                Provider settings
            </button>
        </div>
    </x-ui.panel>
</div>
