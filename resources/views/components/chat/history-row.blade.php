{{--
    One thread in the sidebar list: its row, its inline rename field, and its
    overflow menu.

    This exact block appeared twice — once inside a project, once in the
    date-grouped list — which meant every change to the row had to be made
    twice and the two were already drifting. Rendering one partial fixes that
    and leaves one definition of what a thread row is.

    `chat` and `historyLayout` are read straight from the Alpine scope rather
    than passed in: this component is only ever rendered from inside an `x-for`,
    and a Blade prop could only ever carry a PHP value, not the live Alpine
    object the row is actually about.
--}}


<div
    class="group/row relative"
    draggable="true"
    x-on:dragstart="draggedConversationId = chat.id"
>
    {{-- Renaming swaps the row for an input in place, so the list never
         reflows and the field lands exactly where the text was. --}}
    <template x-if="renamingId === chat.id">
        <input
            type="text"
            class="w-full rounded-md border border-accent bg-raised px-1.5 py-1 text-[13px] outline-none"
            :id="`rename-${chat.id}`"
            x-model="renameDraft"
            x-on:blur="commitRename(chat)"
            x-on:keydown.enter.prevent="commitRename(chat)"
            x-on:keydown.escape.prevent="cancelRename()"
            :aria-label="`Rename ${chat.title}`"
        >
    </template>

    <template x-if="renamingId !== chat.id">
        <div
            class="whale-row whale-row-hover cursor-pointer py-1.5"
            :class="isGridHistory() ? 'flex-col items-start gap-1 px-2 py-2' : ''"
            :aria-current="activeConversationId === chat.id ? 'page' : null"
            x-on:click="openConversation(chat)"
        >
            <span class="flex w-full min-w-0 items-center gap-1.5">
                <x-ui.icon x-show="chat.pinned" name="pin" class="h-3 w-3 shrink-0 text-accent" />

                <span class="min-w-0 flex-1 truncate font-medium" x-text="chat.title"></span>
            </span>

            {{-- The preview is what the extra space in grid mode is for, so it
                 only appears there rather than duplicating the title. --}}
            <span
                x-show="isGridHistory() && chat.preview"
                class="line-clamp-2 w-full text-[11px] leading-snug text-muted"
                x-text="chat.preview"
            ></span>

            <button
                type="button"
                class="whale-icon-button-sm hidden group-hover/row:grid"
                x-on:click.stop="menuFor = menuFor === chat.id ? null : chat.id"
                :aria-expanded="menuFor === chat.id ? 'true' : 'false'"
                :aria-label="`Actions for ${chat.title}`"
            >
                <x-ui.icon name="more" class="h-3.5 w-3.5" />
            </button>
        </div>
    </template>

    <x-ui.panel show="menuFor === chat.id" hide="menuFor = null" width="w-52" label="Chat actions">
        <x-ui.menu-item
            icon="pin"
            x-on:click="togglePinned(chat); menuFor = null"
        >
            <span class="block truncate" x-text="chat.pinned ? 'Unpin' : 'Pin to top'"></span>

            <x-slot:suffix>
                <span class="font-mono text-[10px] uppercase text-faint" x-text="chat.pinned ? 'unpin' : 'pin'"></span>
            </x-slot:suffix>
        </x-ui.menu-item>

        <x-ui.menu-item icon="pencil" x-on:click="startRename(chat)">
            <span class="block truncate">Rename</span>
        </x-ui.menu-item>

        <x-ui.menu-item icon="archive" x-on:click="archiveConversation(chat)">
            <span class="block truncate">Archive</span>
        </x-ui.menu-item>

        <x-ui.menu-item icon="trash" danger x-on:click="deleteConversation(chat)">
            <span class="block truncate">Delete</span>
        </x-ui.menu-item>
    </x-ui.panel>
</div>