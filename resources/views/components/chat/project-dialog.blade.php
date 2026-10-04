{{-- Create a project to group chats in the sidebar. --}}
<div
    x-show="projectDialogOpen"
    x-transition:enter="transition ease-out duration-150"
    x-transition:enter-start="opacity-0"
    x-transition:enter-end="opacity-100"
    x-transition:leave="transition ease-in duration-100"
    x-transition:leave-start="opacity-100"
    x-transition:leave-end="opacity-0"
    class="fixed inset-0 z-50 flex items-center justify-center p-4"
    style="display:none"
    role="dialog"
    aria-modal="true"
    aria-label="New project"
>
    <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" x-on:click="projectDialogOpen = false"></div>

    <div class="relative w-full max-w-sm rounded-2xl border border-black/[0.08] bg-white p-5 shadow-2xl
                dark:border-white/[0.12] dark:bg-[#212121]">
        <h2 class="text-sm font-semibold">New project</h2>
        <p class="mt-1 text-xs text-[#8f8f8f]">Group related chats together. Drag a chat onto a project to file it.</p>

        <input
            type="text"
            x-model="projectDraft"
            x-on:keydown.enter.prevent="createProject()"
            x-on:keydown.escape.prevent="projectDialogOpen = false"
            maxlength="80"
            placeholder="Project name"
            class="mt-4 w-full rounded-xl border border-black/[0.1] bg-white px-3 py-2 text-sm
                   focus:border-accent focus:outline-none focus:ring-2 focus:ring-accent/20
                   dark:border-white/[0.14] dark:bg-[#303030]"
        >

        <div class="mt-4 flex justify-end gap-2">
            <button type="button" x-on:click="projectDialogOpen = false"
                    class="rounded-lg px-3 py-1.5 text-xs font-medium text-[#5d5d5d] transition
                           hover:bg-black/[0.05] dark:text-[#b4b4b4] dark:hover:bg-white/[0.08]">
                Cancel
            </button>
            <button type="button" x-on:click="createProject()" :disabled="!projectDraft.trim()"
                    class="rounded-lg bg-[#0d0d0d] px-3 py-1.5 text-xs font-medium text-white transition
                           hover:bg-black/80 disabled:cursor-not-allowed disabled:opacity-40
                           dark:bg-white dark:text-[#0d0d0d] dark:hover:bg-[#ececec]">
                Create
            </button>
        </div>
    </div>
</div>
