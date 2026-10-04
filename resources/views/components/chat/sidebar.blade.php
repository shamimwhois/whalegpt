<div
    x-show="mobileSidebar"
    x-transition.opacity
    x-on:click="mobileSidebar = false"
    class="fixed inset-0 z-30 bg-black/50 backdrop-blur-sm lg:hidden"
    style="display:none"
></div>

<aside
    class="fixed inset-y-0 left-0 z-40 flex w-[260px] shrink-0 flex-col bg-[#f9f9f9]
           transition-transform duration-300 ease-out dark:bg-[#181818]
           lg:static lg:translate-x-0"
    :class="mobileSidebar ? 'translate-x-0 shadow-2xl' : '-translate-x-full lg:translate-x-0'"
>
    <div class="flex h-14 items-center gap-2.5 px-3">
        <div class="grid h-8 w-8 place-items-center rounded-lg bg-accent text-white">
            <svg class="h-[18px] w-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                <path stroke-linecap="round" stroke-linejoin="round"
                      d="M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 0 0-3.09 3.09Z"/>
            </svg>
        </div>
        <p class="min-w-0 flex-1 truncate text-sm font-semibold tracking-[-0.01em]">Whale AI</p>
        <button
            x-on:click="mobileSidebar = false"
            class="grid h-8 w-8 place-items-center rounded-lg text-[#5d5d5d] transition
                   hover:bg-black/[0.05] focus-visible:outline-2 focus-visible:outline-accent
                   dark:text-[#b4b4b4] dark:hover:bg-white/[0.08] lg:hidden"
            aria-label="Close sidebar"
        >
            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" d="M6 6l12 12M18 6 6 18"/>
            </svg>
        </button>
    </div>

    <div class="space-y-0.5 px-3">
        <a href="{{ route('chat.index') }}"
           class="flex w-full items-center gap-2.5 rounded-lg px-2.5 py-2 text-sm font-medium
                  transition hover:bg-black/[0.05] focus-visible:outline-2 focus-visible:outline-accent
                  dark:hover:bg-white/[0.08]">
            <svg class="h-4 w-4 text-[#5d5d5d] dark:text-[#b4b4b4]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                <path stroke-linecap="round" stroke-linejoin="round"
                      d="M16.862 4.487l1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Z"/>
            </svg>
            New chat
        </a>

        <a href="{{ route('chat.studio') }}"
           class="flex w-full items-center gap-2.5 rounded-lg px-2.5 py-2 text-sm font-medium
                  transition hover:bg-black/[0.05] focus-visible:outline-2 focus-visible:outline-accent
                  dark:hover:bg-white/[0.08]">
            <svg class="h-4 w-4 text-[#5d5d5d] dark:text-[#b4b4b4]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                <path stroke-linecap="round" stroke-linejoin="round" d="M4 5h16v14H4zM4 15l4-4 4 4 3-3 5 5"/>
            </svg>
            Studio
        </a>

        <a href="{{ route('chat.workspace') }}"
           class="flex w-full items-center gap-2.5 rounded-lg px-2.5 py-2 text-sm font-medium
                  transition hover:bg-black/[0.05] focus-visible:outline-2 focus-visible:outline-accent
                  dark:hover:bg-white/[0.08]">
            <svg class="h-4 w-4 text-[#5d5d5d] dark:text-[#b4b4b4]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                <path stroke-linecap="round" stroke-linejoin="round"
                      d="M3 7a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V7Z"/>
            </svg>
            Workspace
        </a>
    </div>

    <div class="px-3 pt-1">
        <div class="relative">
            <svg class="pointer-events-none absolute left-2.5 top-1/2 h-4 w-4 -translate-y-1/2 text-[#8f8f8f]"
                 viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="11" cy="11" r="7"/><path stroke-linecap="round" d="m20 20-3.5-3.5"/>
            </svg>
            <input
                type="search"
                x-model="searchQuery"
                aria-label="Search conversations"
                placeholder="Search chats"
                class="w-full rounded-lg bg-transparent py-2 pl-8 pr-3 text-sm
                       placeholder:text-[#8f8f8f]
                       hover:bg-black/[0.05] focus:bg-black/[0.05] focus:outline-none
                       dark:text-[#ececec] dark:hover:bg-white/[0.08] dark:focus:bg-white/[0.08]"
            >
        </div>
    </div>
    <nav aria-label="Chat history" class="whale-scroll mt-4 flex-1 space-y-0.5 overflow-y-auto px-3 pb-4">
        <div class="flex items-center justify-between px-2.5 pb-1">
            <span class="text-xs font-medium text-[#8f8f8f]">Projects</span>
            <button type="button" x-on:click="projectDialogOpen = true" title="New project"
                    class="grid h-6 w-6 place-items-center rounded-md text-[#8f8f8f] transition
                           hover:bg-black/[0.06] dark:hover:bg-white/[0.08]">
                <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" d="M12 5v14M5 12h14"/>
                </svg>
            </button>
        </div>

        {{-- A project accepts a dropped chat to file it. --}}
        <template x-for="project in projects" :key="project.id">
            <div
                class="rounded-lg"
                x-on:dragover.prevent
                x-on:drop.prevent="dropConversation(project.id)"
            >
                <div class="group flex items-center gap-2 rounded-lg px-2.5 py-1.5 text-sm
                            transition hover:bg-black/[0.05] dark:hover:bg-white/[0.08]">
                    <span class="h-2 w-2 shrink-0 rounded-full bg-accent"></span>
                    <span class="min-w-0 flex-1 truncate font-medium text-[#5d5d5d] dark:text-[#b4b4b4]"
                          x-text="project.name"></span>
                    <button type="button" x-on:click="renameProject(project)" title="Rename"
                            class="hidden h-5 w-5 place-items-center rounded text-[#8f8f8f] group-hover:grid">
                        <svg class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m16.9 4.5 2.6 2.6L8.6 18 5 19l1-3.6Z"/>
                        </svg>
                    </button>
                    <button type="button" x-on:click="deleteProject(project)" title="Delete"
                            class="hidden h-5 w-5 place-items-center rounded text-[#8f8f8f] group-hover:grid">
                        <svg class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" d="M6 6l12 12M18 6 6 18"/>
                        </svg>
                    </button>
                </div>

                <div class="ml-3 border-l border-black/[0.08] pl-2 dark:border-white/[0.1]">
                    <template x-for="chat in project.conversations" :key="chat.id">
                        <div class="group flex cursor-pointer items-center gap-2 rounded-lg px-2.5 py-1.5 text-sm
                                    transition hover:bg-black/[0.05] dark:hover:bg-white/[0.08]"
                             draggable="true"
                             x-on:dragstart="draggedConversationId = chat.id"
                             x-on:click="openConversation(chat)"
                             :class="activeConversationId === chat.id ? 'bg-black/[0.06] dark:bg-white/[0.08]' : ''">
                            <span class="min-w-0 flex-1 truncate text-[#5d5d5d] dark:text-[#b4b4b4]" x-text="chat.title"></span>
                        </div>
                    </template>
                </div>
            </div>
        </template>

        <p class="px-2.5 pb-1 pt-3 text-xs font-medium text-[#8f8f8f]">Chats</p>

        <template x-if="conversations.length === 0 && projects.length === 0">
            <p class="px-2.5 py-2 text-sm text-[#8f8f8f]">No chats yet</p>
        </template>

        <template x-for="chat in conversations" :key="chat.id">
            <div
                class="group flex cursor-pointer items-center gap-2 rounded-lg px-2.5 py-2 text-sm transition"
                draggable="true"
                x-on:dragstart="draggedConversationId = chat.id"
                x-on:click="openConversation(chat)"
                x-show="chat.title.toLowerCase().includes(searchQuery.trim().toLowerCase())"
                :class="activeConversationId === chat.id
                    ? 'bg-black/[0.06] dark:bg-white/[0.08]'
                    : 'hover:bg-black/[0.05] dark:hover:bg-white/[0.08]'"
            >
                <svg x-show="chat.pinned" class="h-3 w-3 shrink-0 text-accent" viewBox="0 0 24 24"
                     fill="none" stroke="currentColor" stroke-width="2" style="display:none">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 3h6l-1 7 3 3H7l3-3-1-7Z"/>
                </svg>
                <span class="min-w-0 flex-1 truncate font-medium text-[#5d5d5d] dark:text-[#b4b4b4]"
                      x-text="chat.title"></span>

                <div class="hidden items-center gap-0.5 group-hover:flex">
                    <button type="button" x-on:click.stop="togglePinned(chat)" title="Pin"
                            class="grid h-5 w-5 place-items-center rounded text-[#8f8f8f] hover:bg-black/[0.08] dark:hover:bg-white/[0.12]">
                        <svg class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 3h6l-1 7 3 3H7l3-3-1-7Z"/>
                        </svg>
                    </button>
                    <button type="button" x-on:click.stop="renameConversation(chat)" title="Rename"
                            class="grid h-5 w-5 place-items-center rounded text-[#8f8f8f] hover:bg-black/[0.08] dark:hover:bg-white/[0.12]">
                        <svg class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m16.9 4.5 2.6 2.6L8.6 18 5 19l1-3.6Z"/>
                        </svg>
                    </button>
                    <button type="button" x-on:click.stop="deleteConversation(chat)" title="Delete"
                            class="grid h-5 w-5 place-items-center rounded text-[#8f8f8f] hover:bg-black/[0.08] dark:hover:bg-white/[0.12]">
                        <svg class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" d="M6 6l12 12M18 6 6 18"/>
                        </svg>
                    </button>
                </div>
            </div>
        </template>
    </nav>

    <div class="border-t border-black/[0.06] p-2 dark:border-white/[0.08]">
        <div class="flex items-center gap-2.5 rounded-lg px-2.5 py-2
                    hover:bg-black/[0.05] dark:hover:bg-white/[0.08]">
            <img
                src="https://i.pravatar.cc/80?img=12"
                alt=""
                class="h-7 w-7 rounded-full"
            >
            <div class="min-w-0 flex-1">
                <p class="truncate text-sm font-medium">Alex Rivera</p>
            </div>

            <button
                x-on:click="
                    document.documentElement.classList.toggle('dark');
                    localStorage.theme = document.documentElement.classList.contains('dark') ? 'dark' : 'light';
                "
                class="grid h-8 w-8 place-items-center rounded-lg text-[#5d5d5d] transition
                       hover:bg-black/[0.05] focus-visible:outline-2 focus-visible:outline-accent
                       dark:text-[#b4b4b4] dark:hover:bg-white/[0.08]"
                aria-label="Toggle theme"
            >
                <svg class="h-4 w-4 dark:hidden" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79Z"/>
                </svg>
                <svg class="hidden h-4 w-4 dark:block" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="4"/>
                    <path stroke-linecap="round" d="M12 2v2m0 16v2M4.9 4.9l1.4 1.4m11.4 11.4 1.4 1.4M2 12h2m16 0h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/>
                </svg>
            </button>
        </div>
    </div>
</aside>