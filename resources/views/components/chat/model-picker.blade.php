{{-- Model picker: only providers and models this app can actually run. --}}
<div class="relative min-w-0 flex-1">
    <button
        x-on:click="pickerOpen = !pickerOpen"
        x-on:click.outside="pickerOpen = false"
        :aria-expanded="pickerOpen ? 'true' : 'false'"
        class="flex max-w-full items-center gap-1.5 rounded-lg px-2 py-1.5 text-sm font-medium
               transition hover:bg-black/[0.05] focus-visible:outline-2 focus-visible:outline-accent
               dark:hover:bg-white/[0.08]"
    >            <span class="truncate" x-text="modelLabel()">Loading…</span>
            <span
                x-show="modelFilter !== 'all'"
                class="hidden shrink-0 rounded-full bg-black/[0.06] px-1.5 py-0.5 text-[10px] font-medium
                       text-[#5d5d5d] sm:inline dark:bg-white/10 dark:text-[#b4b4b4]"
                style="display:none"
                x-text="modelFilterOptions().find((option) => option.id === modelFilter)?.label"
            ></span>
        <svg class="h-3.5 w-3.5 shrink-0 opacity-60" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5"/>
        </svg>
    </button>

    <div
        x-show="pickerOpen"
        x-transition:enter="transition ease-out duration-150"
        x-transition:enter-start="opacity-0 -translate-y-1"
        x-transition:enter-end="opacity-100 translate-y-0"
        class="absolute left-0 top-11 z-40 w-72 overflow-hidden rounded-2xl border border-black/[0.08]
               bg-white shadow-xl dark:border-white/[0.14] dark:bg-[#303030]"
        style="display:none"
    >
        {{-- Type filter: narrows the list to models of a kind, so a reasoning
             model never has to be hunted past the fast ones. --}}
        <div class="flex gap-1 border-b border-black/[0.08] p-1.5 dark:border-white/[0.12]">
            <template x-for="option in modelFilterOptions()" :key="option.id">
                <button
                    type="button"
                    x-on:click="modelFilter = option.id"
                    class="rounded-md px-2 py-1 text-[11px] font-medium transition focus-visible:outline-2
                           focus-visible:outline-accent"
                    :class="modelFilter === option.id
                        ? 'bg-black/[0.08] text-[#0d0d0d] dark:bg-white/[0.14] dark:text-white'
                        : 'text-[#8f8f8f] hover:bg-black/[0.05] dark:hover:bg-white/[0.08]'"
                    x-text="option.label"
                ></button>
            </template>

            <span class="ml-auto self-center pr-1 text-[10px] tabular-nums text-[#8f8f8f]">
                <span x-text="visibleModelCount()"></span>
            </span>
        </div>

        <div class="whale-scroll max-h-80 overflow-y-auto p-1.5">
            <p
                x-show="visibleModelCount() === 0"
                class="px-2.5 py-3 text-[12px] leading-snug text-[#8f8f8f]"
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
                            :class="provider.configured ? 'bg-accent' : 'bg-[#8f8f8f]'"
                        ></span>
                        <span
                            class="truncate text-xs font-semibold"
                            :class="provider.configured
                                ? 'text-[#0d0d0d] dark:text-white'
                                : 'text-[#8f8f8f]'"
                            x-text="provider.label"
                        ></span>
                        <span
                            x-show="provider.default"
                            class="shrink-0 rounded-full bg-black/[0.06] px-1.5 py-0.5 text-[10px]
                                   font-medium text-[#5d5d5d] dark:bg-white/10 dark:text-[#b4b4b4]"
                        >default</span>
                    </div>

                    <template x-for="model in provider.models" :key="provider.name + model.id">
                        <button
                            type="button"
                            x-show="modelVisible(model)"
                            x-on:click="selectModel(provider.name, model.id); pickerOpen = false"
                            class="flex w-full items-center gap-2 rounded-lg px-2.5 py-2 text-left text-sm
                                   transition hover:bg-black/[0.05] focus-visible:outline-2
                                   focus-visible:outline-accent dark:text-[#b4b4b4]
                                   dark:hover:bg-white/[0.08]"
                        >
                            <span class="min-w-0 flex-1 truncate text-[#5d5d5d]" x-text="model.label"></span>
                            <svg
                                x-show="provider.name === providerName && model.id === modelName"
                                class="h-4 w-4 shrink-0 text-accent"
                                viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"
                            >
                                <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/>
                            </svg>
                        </button>
                    </template>

                    <p
                        x-show="!provider.configured && modelFilter === 'all'"
                        class="px-2.5 pb-1.5 text-[11px] leading-snug text-[#8f8f8f]"
                    >
                        Set <span class="font-mono" x-text="provider.environment"></span> to use this provider.
                    </p>
                </div>
            </template>
<div class="border-t border-black/[0.08] p-1.5 dark:border-white/[0.12]">
            <button
                type="button"
                x-on:click="pickerOpen = false; settingsOpen = true; loadLocalModels()"
                class="flex w-full items-center gap-2 rounded-lg px-2.5 py-2 text-left text-sm
                       text-[#5d5d5d] transition hover:bg-black/[0.05] focus-visible:outline-2
                       focus-visible:outline-accent dark:text-[#b4b4b4] dark:hover:bg-white/[0.08]"
            >
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.324.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 0 1 1.37.49l1.296 2.247a1.125 1.125 0 0 1-.26 1.431l-1.003.827c-.293.241-.438.613-.43.992a6.759 6.759 0 0 1 0 .255c-.008.378.137.75.43.991l1.004.827c.424.35.534.955.26 1.43l-1.298 2.247a1.125 1.125 0 0 1-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.57 6.57 0 0 1-.22.128c-.331.183-.581.495-.644.869l-.213 1.281c-.09.543-.56.94-1.11.94h-2.594c-.55 0-1.019-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 0 1-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 0 1-1.369-.49l-1.297-2.247a1.125 1.125 0 0 1 .26-1.431l1.004-.827c.292-.24.437-.613.43-.991a6.932 6.932 0 0 1 0-.255c.007-.38-.138-.751-.43-.992l-1.004-.827a1.125 1.125 0 0 1-.26-1.43l1.297-2.247a1.125 1.125 0 0 1 1.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.086.22-.128.332-.183.582-.495.644-.869l.214-1.28Z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/>
                </svg>
                Provider settings
            </button>
        </div>
    </div>

    <h1 class="sr-only">Getting started with Laravel</h1>
</div>
        </div>