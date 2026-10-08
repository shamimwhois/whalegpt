{{--
    The context bar.

    It answers "what am I talking to, and what can I do with it": which model,
    which specialist mode, and the two actions that are not about writing a
    message.

    Response depth, thinking effort and response length deliberately live in the
    composer instead. They used to appear here as well, which meant the same
    value had two controls in view at once and both dropdowns opened together
    because they shared one flag.
--}}
<header class="flex h-14 shrink-0 items-center gap-1 px-3 sm:px-4">

    <button
        type="button"
        x-on:click="mobileSidebar = true"
        class="whale-icon-button h-9 w-9 lg:hidden"
        aria-label="Open sidebar"
    >
        <x-ui.icon name="menu" class="h-5 w-5" />
    </button>

    <x-chat.model-picker />

    {{-- Mode selector: which specialist the assistant may delegate to. --}}
    <div class="relative">
        <button
            type="button"
            x-on:click="modeOpen = !modeOpen"
            :aria-expanded="modeOpen ? 'true' : 'false'"
            class="flex items-center gap-1.5 rounded-lg px-2 py-1.5 text-xs font-medium capitalize
                   text-body transition hover:bg-wash focus-visible:outline-2 focus-visible:outline-accent"
            title="Assistant mode"
        >
            <span x-text="modeLabel()"></span>
            <x-ui.icon name="chevron-down" class="h-3 w-3 opacity-60" />
        </button>

        <x-ui.panel show="modeOpen" align="right" label="Assistant mode">
            <template x-for="option in modes" :key="option.id">
                <x-ui.menu-item
                    active="mode === option.id"
                    x-on:click="mode = option.id; modeOpen = false"
                >
                    <span class="block truncate" x-text="option.label"></span>
                    <span class="mt-0.5 block text-[11px] leading-snug text-muted" x-text="option.description"></span>
                </x-ui.menu-item>
            </template>
        </x-ui.panel>
    </div>

    <div class="flex-1"></div>

    <button
        type="button"
        x-on:click="settingsOpen = true"
        title="Model providers"
        class="whale-icon-button h-9 w-9"
        aria-label="Model providers"
    >
        <x-ui.icon name="sliders" class="h-5 w-5" />
    </button>

    <div class="relative hidden sm:block">
        <button
            type="button"
            x-on:click="exportOpen = !exportOpen"
            :aria-expanded="exportOpen ? 'true' : 'false'"
            title="Export conversation"
            class="whale-icon-button h-9 w-9"
            aria-label="Export conversation"
        >
            <x-ui.icon name="download" class="h-4 w-4" />
        </button>

        <x-ui.panel show="exportOpen" align="right" width="w-44" label="Export">
            <template x-for="format in formats" :key="format[0]">
                <x-ui.menu-item x-on:click="exportConversation(format[0]); exportOpen = false">
                    <span class="block truncate" x-text="format[1]"></span>

                    <x-slot:suffix>
                        <span class="font-mono text-[10px] uppercase text-faint" x-text="format[0]"></span>
                    </x-slot:suffix>
                </x-ui.menu-item>
            </template>

            <div class="my-1 border-t border-line"></div>

            <x-ui.menu-item
                x-bind:disabled="! activeConversationId"
                x-on:click="shareConversation(); exportOpen = false"
            >
                <span class="block truncate" x-text="shareActiveUrl() ? 'Copy share link' : 'Share conversation'"></span>

                <x-slot:suffix>
                    <span class="font-mono text-[10px] uppercase text-faint" x-text="shareActiveUrl() ? 'copy' : 'share'"></span>
                </x-slot:suffix>
            </x-ui.menu-item>

            <x-ui.menu-item
                x-show="shareActiveUrl()"
                danger
                x-on:click="stopSharing(); exportOpen = false"
            >
                <span class="block truncate">Stop sharing</span>

                <x-slot:suffix>
                    <span class="font-mono text-[10px] uppercase text-faint">off</span>
                </x-slot:suffix>
            </x-ui.menu-item>
        </x-ui.panel>
    </div>
</header>