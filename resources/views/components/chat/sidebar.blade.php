@use('Illuminate\Support\Str')

{{--
    The navigation rail.

    Three states share this one element:

      - mobile (below lg)  an overlay drawer, dismissed by tapping the backdrop
      - desktop, expanded  the full column, resizable by dragging its right edge
      - desktop, collapsed a 56px icon rail

    Collapsing deliberately does not pretend to show chat titles in 56px. It
    keeps only the destinations, because a rail full of truncated words is
    worse than no rail at all.
--}}

{{-- Mobile backdrop. --}}
<div
    x-show="mobileSidebar"
    x-transition.opacity
    x-on:click="mobileSidebar = false"
    class="fixed inset-0 z-30 bg-black/50 backdrop-blur-sm lg:hidden"
    style="display:none"
></div>

<aside
    x-on:keydown="onSidebarKeydown($event)"
    class="group/sidebar fixed inset-y-0 left-0 z-40 flex w-[280px] shrink-0 flex-col border-r border-line
           bg-sidebar transition-[width,transform] duration-300 ease-out
           lg:static lg:w-[var(--sidebar-width)] lg:translate-x-0"
    :style="`--sidebar-width: ${sidebarCollapsed ? 56 : sidebarWidth}px`"
    :class="mobileSidebar ? 'translate-x-0 shadow-2xl' : '-translate-x-full lg:translate-x-0'"
    aria-label="Sidebar"
>

    {{-- Drag handle: only on desktop, only while expanded, and only visible
         on hover so it does not compete with the content it sits against. --}}
    <div
        x-show="! sidebarCollapsed"
        x-on:pointerdown="startSidebarResize($event)"
        class="absolute -right-1.5 top-0 z-10 hidden h-full w-3 cursor-col-resize touch-none
               lg:block"
        :class="resizingSidebar ? 'after:w-0.5 after:bg-accent' : 'after:w-0.5 after:border-line hover:after:bg-accent'"
        role="separator"
        aria-orientation="vertical"
        :aria-valuenow="sidebarWidth"
        :aria-valuemin="200"
        :aria-valuemax="380"
        aria-label="Resize sidebar"
        tabindex="0"
        x-on:keydown.left.prevent="setSidebarWidth(sidebarWidth - 16)"
        x-on:keydown.right.prevent="setSidebarWidth(sidebarWidth + 16)"
    >
        {{-- While dragging, the resize must win over every hover transition on
             the page, so it is suppressed globally for the duration. --}}
        <template x-if="resizingSidebar">
            <style>.whale-scroll { scrollbar-width: none; }</style>
        </template>
    </div>

    {{-- Brand row. --}}
    <div class="flex h-14 shrink-0 items-center gap-2.5 px-3">
        <div class="grid h-8 w-8 shrink-0 place-items-center rounded-lg bg-accent text-white">
            <x-ui.icon name="sparkles" class="h-[18px] w-[18px]" />
        </div>

        <p
            class="min-w-0 flex-1 truncate text-sm font-semibold tracking-[-0.01em]"
            x-show="! sidebarCollapsed"
        >
            Whale AI
        </p>

        <button
            type="button"
            x-on:click="toggleSidebar()"
            class="whale-icon-button lg:hidden"
            aria-label="Close sidebar"
        >
            <x-ui.icon name="close" class="h-4 w-4" />
        </button>
    </div>

    {{-- Destinations. In the rail there is no room for labels, so the rows drop
         their padding and centre the icon instead of letting the text clip. --}}
    <nav class="shrink-0 space-y-0.5 px-3" aria-label="Main">
        <a
            href="{{ route('chat.index') }}"
            class="whale-row whale-row-hover py-2 font-medium"
            :class="sidebarCollapsed ? 'justify-center px-0' : 'px-2.5'"
            :title="sidebarCollapsed ? 'New chat' : null"
        >
            <x-ui.icon name="pencil" class="h-4 w-4 shrink-0 text-body" />
            <span x-show="! sidebarCollapsed" class="truncate">New chat</span>
        </a>

        {{-- The thread list lives directly beneath this rail, so the entry
             points at it rather than at a second copy of the page. --}}
        <a
            href="{{ route('chat.index') }}#chats"
            class="whale-row whale-row-hover py-2 font-medium"
            :class="sidebarCollapsed ? 'justify-center px-0' : 'px-2.5'"
            :title="sidebarCollapsed ? 'My chats' : null"
        >
            <x-ui.icon name="rows" class="h-4 w-4 shrink-0 text-body" />
            <span x-show="! sidebarCollapsed" class="truncate">My chats</span>
        </a>

        <a
            href="{{ route('chat.studio') }}"
            class="whale-row whale-row-hover py-2 font-medium"
            :class="sidebarCollapsed ? 'justify-center px-0' : 'px-2.5'"
            :title="sidebarCollapsed ? 'Studio' : null"
        >
            <x-ui.icon name="image" class="h-4 w-4 shrink-0 text-body" />
            <span x-show="! sidebarCollapsed" class="truncate">Studio</span>
        </a>

        <a
            href="{{ route('chat.workspace') }}"
            class="whale-row whale-row-hover py-2 font-medium"
            :class="sidebarCollapsed ? 'justify-center px-0' : 'px-2.5'"
            :title="sidebarCollapsed ? 'Workspace' : null"
        >
            <x-ui.icon name="folder" class="h-4 w-4 shrink-0 text-body" />
            <span x-show="! sidebarCollapsed" class="truncate">Workspace</span>
        </a>

        {{-- The rail has no room for the field, so searching from it opens the
             sidebar instead of hiding a 16px-wide input. --}}
        <button
            type="button"
            x-show="sidebarCollapsed"
            x-on:click="toggleSidebar()"
            class="whale-row whale-row-hover justify-center px-0 py-2"
            title="Search chats"
            aria-label="Search chats"
        >
            <x-ui.icon name="search" class="h-4 w-4 shrink-0 text-body" />
        </button>
    </nav>

    {{-- Search. --}}
    <div class="px-3 pt-3" x-show="! sidebarCollapsed">
        <label class="relative block">
            <x-ui.icon
                name="search"
                class="pointer-events-none absolute left-2.5 top-1/2 h-4 w-4 -translate-y-1/2 text-muted"
            />

            <input
                type="search"
                x-model="searchQuery"
                aria-label="Search conversations"
                placeholder="Search chats"
                x-on:keydown.escape="searchQuery = ''"
                class="w-full rounded-lg bg-transparent py-2 pl-8 text-sm placeholder:text-muted
                       hover:bg-wash focus:bg-wash focus:outline-none"
            >
        </label>
    </div>

    {{-- Thread list. --}}
    <nav
        id="chats"
        aria-label="Chat history"
        class="whale-scroll mt-3 flex-1 space-y-0.5 overflow-y-auto px-3 pb-4"
    >
        {{-- Projects. --}}
        <div x-show="! sidebarCollapsed && visibleProjects().length > 0">
            <div class="flex items-center justify-between px-2.5 pb-1">
                <span class="whale-section-label px-0 pb-0">Projects</span>

                <button
                    type="button"
                    x-on:click="projectDialogOpen = true"
                    title="New project"
                    class="whale-icon-button-sm"
                    aria-label="New project"
                >
                    <x-ui.icon name="plus" class="h-3.5 w-3.5" />
                </button>
            </div>

            <template x-for="project in visibleProjects()" :key="project.id">
                {{-- `relative` so the project menu anchors to its own row rather
                     than to whatever ancestor happens to be positioned. --}}
                <div
                    class="relative rounded-lg"
                    x-on:dragover.prevent
                    x-on:drop.prevent="dropConversation(project.id)"
                >
                    <div class="group/project flex items-center gap-2 px-2.5 py-1.5 text-sm">
                        <span class="h-2 w-2 shrink-0 rounded-full bg-accent"></span>

                        <template x-if="renamingProjectId !== project.id">
                            <span class="min-w-0 flex-1 truncate font-medium text-body" x-text="project.name"></span>
                        </template>

                        <template x-if="renamingProjectId === project.id">
                            <input
                                type="text"
                                class="min-w-0 flex-1 rounded-md border border-accent bg-raised px-1.5 py-0.5
                                       text-sm outline-none"
                                :id="`rename-project-${project.id}`"
                                x-model="projectRenameDraft"
                                x-on:blur="commitProjectRename(project)"
                                x-on:keydown.enter.prevent="commitProjectRename(project)"
                                x-on:keydown.escape.prevent="cancelProjectRename()"
                                :aria-label="`Rename ${project.name}`"
                            >
                        </template>

                        <div class="hidden shrink-0 items-center group-hover/project:flex">
                            <button
                                type="button"
                                x-on:click="menuFor = menuFor === 'project:' + project.id ? null : 'project:' + project.id"
                                class="whale-icon-button-sm"
                                :aria-expanded="menuFor === 'project:' + project.id ? 'true' : 'false'"
                                aria-label="Project actions"
                            >
                                <x-ui.icon name="more" class="h-3.5 w-3.5" />
                            </button>
                        </div>
                    </div>

                    <x-ui.panel
                        show="menuFor === 'project:' + project.id"
                        hide="menuFor = null"
                        width="w-48"
                        label="Project actions"
                    >
                        <x-ui.menu-item icon="pencil" x-on:click="renameProject(project)">
                            <span class="block truncate">Rename</span>
                        </x-ui.menu-item>

                        <x-ui.menu-item icon="trash" danger x-on:click="deleteProject(project)">
                            <span class="block truncate">Delete project</span>
                        </x-ui.menu-item>
                    </x-ui.panel>

                    <div class="ml-3 border-l border-line pl-2" :class="historyLayoutClass()">
                        <template x-for="chat in visibleProjectConversations(project)" :key="chat.id">
                            <x-chat.history-row />
                        </template>
                    </div>
                </div>
            </template>
        </div>

        {{-- Chats, grouped by date. --}}
        <div class="pt-3" x-show="! sidebarCollapsed && conversationGroups().length > 0">
            <div class="flex items-center justify-between px-2.5 pb-1">
                <span class="whale-section-label px-0 pb-0">Chats</span>

                <button
                    type="button"
                    x-on:click="toggleHistoryLayout()"
                    class="whale-icon-button-sm"
                    :aria-label="historyLayout === 'grid' ? 'Show chats as a list' : 'Show chats as a grid'"
                    :title="historyLayout === 'grid' ? 'Show as list' : 'Show as grid'"
                >
                    <x-ui.icon x-show="historyLayout !== 'grid'" name="grid" class="h-3.5 w-3.5" />
                    <x-ui.icon x-show="historyLayout === 'grid'" name="list" class="h-3.5 w-3.5" />
                </button>
            </div>

            <template x-for="group in conversationGroups()" :key="group.label">
                <div>
                    <p class="whale-section-label px-2.5 pb-0.5 pt-1.5" x-text="group.label"></p>

                    {{-- The layout class sits on the list of chats, not on the
                         nav above it, so the labels stay full width instead of
                         becoming cells in the two-column grid. --}}
                    <div :class="historyLayoutClass()">
                        <template x-for="chat in group.chats" :key="chat.id">
                            <x-chat.history-row />
                        </template>
                    </div>
                </div>
            </template>
        </div>

        {{-- Empty states. Search and emptiness are different messages: one says
             "nothing matched", the other says "nothing here yet". --}}
        <p
            x-show="! sidebarCollapsed && ! hasSearchResults()"
            class="px-2.5 py-2 text-[13px] text-muted"
            x-text="searchQuery.trim() ? 'No chats match that search' : 'No chats yet'"
        ></p>

        {{-- Archive. --}}
        <div class="pt-3" x-show="! sidebarCollapsed && archivedGroups().length > 0">
            <p class="whale-section-label px-2.5 pb-0.5">Archive</p>

            <template x-for="group in archivedGroups()" :key="group.label">
                <div>
                    <p class="px-2.5 pb-0.5 pt-1.5 text-[11px] text-faint" x-text="group.label"></p>

                    <div :class="historyLayoutClass()">
                        <template x-for="chat in group.chats" :key="chat.id">
                            <div class="group/row relative">
                                <div
                                    class="whale-row whale-row-hover min-w-0 flex-1 cursor-pointer py-1.5 opacity-70
                                           hover:opacity-100"
                                    :class="isGridHistory() ? 'flex-col items-start gap-1 px-2 py-2' : ''"
                                    x-on:click="openConversation(chat)"
                                >
                                    <span class="flex w-full min-w-0 items-center gap-1.5">
                                        <x-ui.icon name="archive" class="h-3.5 w-3.5 shrink-0 text-muted" />

                                        <span class="min-w-0 flex-1 truncate" x-text="chat.title"></span>
                                    </span>

                                    <span
                                        x-show="isGridHistory() && chat.preview"
                                        class="line-clamp-2 w-full text-[11px] leading-snug text-muted"
                                        x-text="chat.preview"
                                    ></span>

                                    <span
                                        x-show="! isGridHistory()"
                                        class="shrink-0 text-[11px] text-faint"
                                        x-text="chat.updated_human"
                                    ></span>
                                </div>

                                <button
                                    type="button"
                                    class="whale-icon-button-sm hidden group-hover/row:grid"
                                    x-on:click="restoreConversation(chat)"
                                    title="Restore"
                                    :aria-label="`Restore ${chat.title}`"
                                >
                                    <x-ui.icon name="refresh" class="h-3.5 w-3.5" />
                                </button>
                            </div>
                        </template>
                    </div>
                </div>
            </template>
        </div>
    </nav>

    {{-- Footer. Stacks in the rail, where two 32px controls cannot sit side by
         side inside the 40px the padding leaves. --}}
    <div class="shrink-0 border-t border-line p-2">
        <div class="flex items-center" :class="sidebarCollapsed ? 'flex-col gap-1' : 'gap-2.5'">
            {{-- The signed-in user, not a placeholder: the chat page refuses a
                 guest, so whoever is here is the only person it can ever show.
                 The avatar is initials rather than a remote image, which keeps
                 the sidebar working offline and sends no third-party request. --}}
            @php
                $user = auth()->user();
                $initials = $user
                    ? Str::of($user->name)
                        ->explode(' ')
                        ->filter()
                        ->take(2)
                        ->map(fn (string $part): string => Str::upper(Str::substr($part, 0, 1)))
                        ->implode('')
                    : null;
            @endphp

            <span
                class="grid h-7 w-7 shrink-0 place-items-center rounded-full bg-wash-strong
                       text-[11px] font-semibold uppercase text-body"
                :title="sidebarCollapsed ? '{{ $user?->name }}' : null"
                aria-hidden="true"
            >{{ $initials ?? '·' }}</span>

            <div class="min-w-0 flex-1" x-show="! sidebarCollapsed">
                <p class="truncate text-sm font-medium">{{ $user?->name ?? 'Not signed in' }}</p>
            </div>

            {{-- Collapsed on desktop: the rail needs its own way back open,
                 because the header's toggle is hidden behind the transcript. --}}
            <button
                type="button"
                x-show="sidebarCollapsed"
                x-on:click="toggleSidebar()"
                class="whale-icon-button"
                title="Expand sidebar"
                aria-label="Expand sidebar"
            >
                <x-ui.icon name="chevron-right" class="h-4 w-4" />
            </button>

            <button
                type="button"
                x-on:click="
                    document.documentElement.classList.toggle('dark');
                    localStorage.theme = document.documentElement.classList.contains('dark') ? 'dark' : 'light';
                "
                class="whale-icon-button"
                aria-label="Toggle theme"
                :title="sidebarCollapsed ? 'Toggle theme' : null"
            >
                <x-ui.icon name="moon" class="h-4 w-4 dark:hidden" />
                <x-ui.icon name="sun" class="h-4 w-4 hidden dark:block" />
            </button>
        </div>
    </div>
</aside>
