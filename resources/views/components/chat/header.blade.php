<header class="flex h-14 shrink-0 items-center gap-1 px-3 sm:px-4">

    <button
        x-on:click="mobileSidebar = true"
        class="grid h-9 w-9 place-items-center rounded-lg text-[#5d5d5d] transition
               hover:bg-black/[0.05] focus-visible:outline-2 focus-visible:outline-accent
               dark:text-[#b4b4b4] dark:hover:bg-white/[0.08] lg:hidden"
        aria-label="Open sidebar"
    >
        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" d="M4 6h16M4 12h16M4 18h16"/>
        </svg>
    </button>

    <x-chat.model-picker />

    {{-- Mode selector: picks which specialist the assistant may delegate to. --}}
    <div class="relative">
        <button
            type="button"
            x-on:click="modeOpen = !modeOpen"
            x-on:click.outside="modeOpen = false"
            :aria-expanded="modeOpen ? 'true' : 'false'"
            class="flex items-center gap-1.5 rounded-lg px-2 py-1.5 text-xs font-medium capitalize
                   text-[#5d5d5d] transition hover:bg-black/[0.05] focus-visible:outline-2
                   focus-visible:outline-accent dark:text-[#b4b4b4] dark:hover:bg-white/[0.08]"
            title="Assistant mode"
        >
            <span x-text="modeLabel()"></span>
            <svg class="h-3 w-3 opacity-60" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5"/>
            </svg>
        </button>

        <div
            x-show="modeOpen"
            x-transition:enter="transition ease-out duration-150"
            x-transition:enter-start="opacity-0 -translate-y-1"
            x-transition:enter-end="opacity-100 translate-y-0"
            class="absolute right-0 top-11 z-40 w-56 overflow-hidden rounded-xl border border-black/[0.08]
                   bg-white p-1 shadow-xl dark:border-white/[0.14] dark:bg-[#303030]"
            style="display:none"
        >
            <template x-for="option in modes" :key="option.id">
                <button
                    type="button"
                    x-on:click="mode = option.id; modeOpen = false"
                    class="flex w-full flex-col gap-0.5 rounded-lg px-3 py-2 text-left transition
                           hover:bg-black/[0.05] focus-visible:outline-2 focus-visible:outline-accent
                           dark:hover:bg-white/[0.08]"
                >
                    <span class="flex items-center gap-2 text-sm font-medium">
                        <span x-text="option.label"></span>
                        <svg x-show="mode === option.id" class="h-3.5 w-3.5 text-accent"
                             viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/>
                        </svg>
                    </span>
                    <span class="text-[11px] leading-snug text-[#8f8f8f]" x-text="option.description"></span>
                </button>
            </template>
        </div>
    </div>

    {{-- Response depth: how much effort the reply should cost. --}}
    <div class="relative">
        <button
            type="button"
            x-on:click="depthOpen = !depthOpen"
            x-on:click.outside="depthOpen = false"
            :aria-expanded="depthOpen ? 'true' : 'false'"
            class="flex items-center gap-1.5 rounded-lg px-2 py-1.5 text-xs font-medium
                   text-[#5d5d5d] transition hover:bg-black/[0.05] focus-visible:outline-2
                   focus-visible:outline-accent dark:text-[#b4b4b4] dark:hover:bg-white/[0.08]"
            title="Response depth"
        >
            <svg class="h-3.5 w-3.5 opacity-70" viewBox="0 0 24 24" fill="none"
                 stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round"
                      d="M3.75 13.5l10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75Z"/>
            </svg>
            <span class="hidden sm:inline" x-text="depthLabel()"></span>
            <svg class="h-3 w-3 opacity-60" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5"/>
            </svg>
        </button>

        <div
            x-show="depthOpen"
            x-transition:enter="transition ease-out duration-150"
            x-transition:enter-start="opacity-0 -translate-y-1"
            x-transition:enter-end="opacity-100 translate-y-0"
            class="absolute right-0 top-11 z-40 w-64 overflow-hidden rounded-xl border border-black/[0.08]
                   bg-white p-1 shadow-xl dark:border-white/[0.14] dark:bg-[#303030]"
            style="display:none"
        >
            <template x-for="option in depths" :key="option.id">
                <button
                    type="button"
                    x-on:click="setDepth(option.id); depthOpen = false"
                    class="flex w-full flex-col gap-0.5 rounded-lg px-3 py-2 text-left transition
                           hover:bg-black/[0.05] focus-visible:outline-2 focus-visible:outline-accent
                           dark:hover:bg-white/[0.08]"
                >
                    <span class="flex items-center gap-2 text-sm font-medium">
                        <span x-text="option.label"></span>
                        <svg x-show="depth === option.id" class="h-3.5 w-3.5 text-accent"
                             viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/>
                        </svg>
                    </span>
                    <span class="text-[11px] leading-snug text-[#8f8f8f]" x-text="option.description"></span>
                </button>
            </template>
        </div>
    </div>

    <div class="flex items-center gap-1">
        <button
            type="button"
            x-on:click="settingsOpen = true"
            title="Model providers"
            class="grid h-9 w-9 place-items-center rounded-lg text-[#5d5d5d] transition
                   hover:bg-black/[0.05] focus-visible:outline-2 focus-visible:outline-accent
                   dark:text-[#b4b4b4] dark:hover:bg-white/[0.08]"
            aria-label="Model providers"
        >
            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round"
                      d="M10.5 6h9.75M10.5 6a1.5 1.5 0 1 1-3 0m3 0a1.5 1.5 0 1 0-3 0M3.75 6H7.5m3 12h9.75m-9.75 0a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m-3.75 0H7.5m9-6h3.75m-3.75 0a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m-9.75 0h9.75"/>
            </svg>
        </button>

        <div class="relative hidden sm:block">
            <button
                type="button"
                x-on:click="exportOpen = !exportOpen"
                x-on:click.outside="exportOpen = false"
                title="Export conversation"
                class="grid h-9 w-9 place-items-center rounded-lg text-[#5d5d5d] transition
                       hover:bg-black/[0.05] focus-visible:outline-2 focus-visible:outline-accent
                       dark:text-[#b4b4b4] dark:hover:bg-white/[0.08]"
            >
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" d="M4 16v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2M12 4v12m0 0-4-4m4 4 4-4"/>
                </svg>
            </button>

            <div
                x-show="exportOpen"
                x-transition:enter="transition ease-out duration-150"
                x-transition:enter-start="opacity-0 -translate-y-1"
                x-transition:enter-end="opacity-100 translate-y-0"
                class="absolute right-0 top-11 z-40 w-40 overflow-hidden rounded-xl border border-black/[0.08]
                       bg-white p-1 shadow-xl dark:border-white/[0.14] dark:bg-[#303030]"
                style="display:none"
            >
                <template x-for="format in [['txt', 'Plain text'], ['md', 'Markdown'], ['html', 'HTML'], ['json', 'JSON'], ['pdf', 'PDF']]" :key="format[0]">
                    <button
                        type="button"
                        x-on:click="exportConversation(format[0]); exportOpen = false"
                        class="flex w-full items-center justify-between rounded-lg px-3 py-2 text-left text-sm
                               transition hover:bg-black/[0.05] dark:hover:bg-white/[0.08]"
                    >
                        <span x-text="format[1]"></span>
                        <span class="font-mono text-[10px] uppercase text-[#8f8f8f]" x-text="format[0]"></span>
                    </button>
                </template>

                <div class="my-1 border-t border-black/[0.06] dark:border-white/[0.1]"></div>

                <button
                    type="button"
                    x-on:click="shareConversation(); exportOpen = false"
                    :disabled="!activeConversationId"
                    class="flex w-full items-center justify-between rounded-lg px-3 py-2 text-left text-sm
                           transition hover:bg-black/[0.05] disabled:cursor-not-allowed disabled:opacity-40
                           dark:hover:bg-white/[0.08]"
                >
                    <span x-text="shareActiveUrl() ? 'Copy share link' : 'Share conversation'"></span>
                    <span class="font-mono text-[10px] uppercase text-[#8f8f8f]" x-text="shareActiveUrl() ? 'copy' : 'share'"></span>
                </button>

                <button
                    type="button"
                    x-show="shareActiveUrl()"
                    x-on:click="stopSharing(); exportOpen = false"
                    style="display:none"
                    class="flex w-full items-center justify-between rounded-lg px-3 py-2 text-left text-sm
                           transition hover:bg-black/[0.05] dark:hover:bg-white/[0.08]"
                >
                    <span class="text-rose-500">Stop sharing</span>
                    <span class="font-mono text-[10px] uppercase text-[#8f8f8f]">off</span>
                </button>
            </div>
        </div>

    </div>
</header>