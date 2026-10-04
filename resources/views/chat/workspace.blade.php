@extends('layouts.app')

@section('title', 'Workspace')

@section('content')
    <div
        x-data="workspaceApp({
            list: @js(route('chat.workspace.index')),
            search: @js(route('chat.workspace.search')),
            mkdir: @js(route('chat.workspace.mkdir')),
            read: @js(route('chat.workspace.show')),
            write: @js(route('chat.workspace.store')),
            upload: @js(route('chat.workspace.upload')),
            move: @js(route('chat.workspace.move')),
            remove: @js(route('chat.workspace.destroy')),
            preview: @js(route('chat.workspace.preview')),
            export: @js(route('chat.workspace.export')),
            terminal: @js(route('chat.workspace.terminal')),
            chat: @js(route('chat.index')),
            studio: @js(route('chat.studio')),
        })"
        x-init="init()"
        class="flex h-dvh flex-col overflow-hidden bg-white text-[#0d0d0d] dark:bg-[#212121] dark:text-[#ececec]"
    >
        {{-- ───────────────────────── Title bar ───────────────────────── --}}
        <header class="flex h-12 shrink-0 items-center gap-2 border-b border-black/[0.08] px-3 dark:border-white/[0.12] sm:px-4">
            <a href="{{ route('chat.index') }}"
               class="flex items-center gap-2 rounded-lg px-2 py-1.5 text-sm font-medium transition
                      hover:bg-black/[0.05] dark:hover:bg-white/[0.08]">
                <span class="grid h-7 w-7 place-items-center rounded-lg bg-accent text-white">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="M3 12c2.5-3.5 6-5.5 9-5.5S18.5 8.5 21 12c-2.5 3.5-6 5.5-9 5.5S5.5 15.5 3 12Z"/>
                    </svg>
                </span>
                <span class="hidden sm:inline">Whale Workspace</span>
            </a>

            <div class="min-w-0 flex-1"></div>

            {{-- Quick open: the same file list the palette searches. --}}
            <button
                type="button"
                x-on:click="openPalette('file')"
                class="hidden min-w-56 items-center gap-2 rounded-lg border border-black/[0.08] bg-black/[0.03]
                       px-2.5 py-1.5 text-xs text-[#8f8f8f] transition hover:bg-black/[0.06]
                       focus-visible:outline-2 focus-visible:outline-accent
                       dark:border-white/[0.12] dark:bg-white/[0.04] dark:hover:bg-white/[0.08] sm:flex"
            >
                <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="11" cy="11" r="7"/><path stroke-linecap="round" d="m20 20-3.5-3.5"/>
                </svg>
                <span class="flex-1 text-left">Go to file</span>
                <kbd class="rounded border border-black/[0.1] px-1 py-0.5 text-[10px] dark:border-white/[0.15]">Ctrl P</kbd>
            </button>

            <button type="button" x-on:click="uploadDialogOpen = true"
                    class="rounded-lg px-2.5 py-1.5 text-xs font-medium text-[#5d5d5d] transition
                           hover:bg-black/[0.05] dark:text-[#b4b4b4] dark:hover:bg-white/[0.08]">
                <span class="hidden sm:inline">Upload</span>
            </button>

            <a :href="exportUrl()"
               class="rounded-lg px-2.5 py-1.5 text-xs font-medium text-[#5d5d5d] transition
                      hover:bg-black/[0.05] dark:text-[#b4b4b4] dark:hover:bg-white/[0.08]">
                <span class="hidden sm:inline">Export</span>
            </a>
        </header>

        <div class="flex min-h-0 flex-1">

            {{-- ───────────────────────── Activity bar ───────────────────────── --}}
            <nav class="hidden w-11 shrink-0 flex-col items-center gap-1 border-r border-black/[0.08] bg-[#f9f9f9]
                        py-2 dark:border-white/[0.12] dark:bg-[#181818] sm:flex"
                 aria-label="Workspace views">
                <button type="button" x-on:click="showPanel('explorer')" title="Explorer (Ctrl+Shift+E)"
                        class="grid h-9 w-9 place-items-center rounded-lg transition focus-visible:outline-2 focus-visible:outline-accent"
                        :class="sidebarOpen && panel === 'explorer'
                            ? 'bg-black/[0.06] text-[#0d0d0d] dark:bg-white/[0.1] dark:text-white'
                            : 'text-[#8f8f8f] hover:bg-black/[0.04] dark:hover:bg-white/[0.06]'">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7">
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="M3.75 6A2.25 2.25 0 0 1 6 3.75h2.25A2.25 2.25 0 0 1 10.5 6v2.25a2.25 2.25 0 0 1-2.25 2.25H6a2.25 2.25 0 0 1-2.25-2.25V6ZM13.5 6A2.25 2.25 0 0 1 15.75 3.75H18A2.25 2.25 0 0 1 20.25 6v2.25A2.25 2.25 0 0 1 18 10.5h-2.25a2.25 2.25 0 0 1-2.25-2.25V6ZM3.75 15.75A2.25 2.25 0 0 1 6 13.5h2.25a2.25 2.25 0 0 1 2.25 2.25V18a2.25 2.25 0 0 1-2.25 2.25H6A2.25 2.25 0 0 1 3.75 18v-2.25ZM13.5 15.75a2.25 2.25 0 0 1 2.25-2.25H18a2.25 2.25 0 0 1 2.25 2.25V18A2.25 2.25 0 0 1 18 20.25h-2.25A2.25 2.25 0 0 1 13.5 18v-2.25Z"/>
                    </svg>
                </button>

                <button type="button" x-on:click="showPanel('search')" title="Search in files (Ctrl+Shift+F)"
                        class="grid h-9 w-9 place-items-center rounded-lg transition focus-visible:outline-2 focus-visible:outline-accent"
                        :class="sidebarOpen && panel === 'search'
                            ? 'bg-black/[0.06] text-[#0d0d0d] dark:bg-white/[0.1] dark:text-white'
                            : 'text-[#8f8f8f] hover:bg-black/[0.04] dark:hover:bg-white/[0.06]'">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7">
                        <circle cx="11" cy="11" r="7"/><path stroke-linecap="round" d="m20 20-3.5-3.5"/>
                    </svg>
                </button>

                <div class="mt-auto flex flex-col items-center gap-1">
                    <a href="{{ route('chat.studio') }}" title="Studio"
                       class="grid h-9 w-9 place-items-center rounded-lg text-[#8f8f8f] transition
                              hover:bg-black/[0.04] dark:hover:bg-white/[0.06]">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 5h16v14H4zM4 15l4-4 4 4 3-3 5 5"/>
                        </svg>
                    </a>
                </div>
            </nav>

            {{-- ───────────────────────── Side panel ───────────────────────── --}}
            <aside
                x-show="sidebarOpen"
                x-transition:enter="transition ease-out duration-150"
                x-transition:enter-start="-translate-x-2 opacity-0"
                x-transition:enter-end="translate-x-0 opacity-100"
                x-transition:leave="transition ease-in duration-100"
                class="flex w-64 shrink-0 flex-col border-r border-black/[0.08] bg-[#f9f9f9]
                       dark:border-white/[0.12] dark:bg-[#181818]"
                style="display:none"
            >
                {{-- Explorer --}}
                <template x-if="panel === 'explorer'">
                    <div class="flex min-h-0 flex-1 flex-col">
                        <div class="flex items-center justify-between px-3 py-2.5">
                            <span class="text-xs font-semibold uppercase tracking-wider text-[#8f8f8f]">Explorer</span>
                            <div class="flex items-center gap-0.5">
                                <button type="button" x-on:click="openNewDialog('file')" title="New file"
                                        class="grid h-7 w-7 place-items-center rounded-md text-[#5d5d5d] transition
                                               hover:bg-black/[0.06] dark:text-[#b4b4b4] dark:hover:bg-white/[0.08]">
                                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                              d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5A3.375 3.375 0 0 0 10.125 2.25H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z"/>
                                    </svg>
                                </button>
                                <button type="button" x-on:click="openNewDialog('folder')" title="New folder"
                                        class="grid h-7 w-7 place-items-center rounded-md text-[#5d5d5d] transition
                                               hover:bg-black/[0.06] dark:text-[#b4b4b4] dark:hover:bg-white/[0.08]">
                                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                              d="M12 10.5v6m3-3H9m3-3v6m-6 6h12a2 2 0 0 0 2-2V6a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2Z"/>
                                    </svg>
                                </button>
                                <button type="button" x-on:click="collapseAll()" title="Collapse all"
                                        class="grid h-7 w-7 place-items-center rounded-md text-[#5d5d5d] transition
                                               hover:bg-black/[0.06] dark:text-[#b4b4b4] dark:hover:bg-white/[0.08]">
                                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m7.5 4.5 5 5 5-5M7.5 19.5l5-5 5 5"/>
                                    </svg>
                                </button>
                                <button type="button" x-on:click="refresh()" title="Refresh"
                                        class="grid h-7 w-7 place-items-center rounded-md text-[#5d5d5d] transition
                                               hover:bg-black/[0.06] dark:text-[#b4b4b4] dark:hover:bg-white/[0.08]">
                                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                              d="M16.023 9.348h4.992V4.356M2.985 19.644v-4.992h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7"/>
                                    </svg>
                                </button>
                            </div>
                        </div>

                        <div class="px-2 pb-2">
                            <input
                                type="search"
                                x-model="treeFilter"
                                aria-label="Filter files"
                                placeholder="Filter by name"
                                class="w-full rounded-md border border-black/[0.08] bg-white px-2 py-1.5 text-xs
                                       placeholder:text-[#8f8f8f] focus:border-accent focus:outline-none
                                       dark:border-white/[0.12] dark:bg-[#242424]"
                            >
                        </div>

                        <div
                            class="whale-scroll min-h-0 flex-1 overflow-y-auto px-1 pb-3"
                            x-on:dragover.prevent="treeDragOver = true"
                            x-on:dragleave="treeDragOver = false"
                            x-on:drop.prevent="handleDrop($event)"
                            x-on:contextmenu.prevent="openContextMenu($event, null)"
                            :class="treeDragOver ? 'bg-accent/10 ring-2 ring-inset ring-accent/40' : ''"
                        >
                            <template x-if="files.length === 0">
                                <p class="px-2 py-3 text-xs leading-relaxed text-[#8f8f8f]">
                                    No files yet. Ask Whale to build something, or drop files here.
                                </p>
                            </template>

                            <template x-if="files.length > 0 && explorerRows.length === 0">
                                <p class="px-2 py-3 text-xs text-[#8f8f8f]">No file matches that filter.</p>
                            </template>

                            <template x-for="row in explorerRows" :key="row.path">
                                <div
                                    draggable="true"
                                    :data-path="row.path"
                                    :data-kind="row.kind"
                                    x-on:dragstart="draggedPath = row.path"
                                    x-on:dragend="draggedPath = null"
                                    x-on:click="row.kind === 'dir' ? toggleDir(row.path) : open(row.path)"
                                    x-on:dblclick.stop="row.kind === 'dir' ? toggleDir(row.path) : null"
                                    x-on:contextmenu.prevent.stop="openContextMenu($event, row.path)"
                                    x-on:dragover.prevent="dragOverPath = row.path"
                                    x-on:dragleave="dragOverPath = null"
                                    class="group relative flex cursor-pointer items-center gap-1.5 rounded-md py-1.5 pr-1.5 text-sm transition"
                                    :class="[
                                        activePath === row.path && row.kind === 'file'
                                            ? 'bg-black/[0.07] dark:bg-white/[0.1]'
                                            : 'hover:bg-black/[0.05] dark:hover:bg-white/[0.06]',
                                        dragOverPath === row.path ? 'bg-accent/15 ring-1 ring-accent/50' : '',
                                    ]"
                                    :style="{ paddingLeft: (8 + row.depth * 14) + 'px' }"
                                >
                                    {{-- Indent guides, the way VS Code draws them. The style binding
                                         has to be an object: a string form would replace the whole
                                         style attribute and wipe x-show's display. --}}
                                    <span
                                        x-show="row.depth > 0"
                                        class="pointer-events-none absolute top-0 h-full w-px bg-black/[0.08]
                                               dark:bg-white/[0.12]"
                                        :style="{ left: (14 + (row.depth - 1) * 14) + 'px' }"
                                        style="display:none"
                                    ></span>

                                    <svg x-show="row.kind === 'dir'" class="h-3.5 w-3.5 shrink-0 text-[#8f8f8f] transition-transform"
                                         :class="isExpanded(row.path) ? 'rotate-90' : ''"
                                         viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" style="display:none">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/>
                                    </svg>

                                    <span x-show="row.kind === 'file'"
                                          class="shrink-0 font-mono text-[10px] font-bold uppercase"
                                          :class="fileTone(row.path)"
                                          x-text="fileBadge(row.path)"></span>

                                    <span class="min-w-0 flex-1 truncate"
                                          :class="row.kind === 'dir' ? 'font-medium text-[#5d5d5d] dark:text-[#b4b4b4]' : 'text-[#0d0d0d] dark:text-[#ececec]'"
                                          x-text="row.name"></span>

                                    <span x-show="row.kind === 'file' && tabFor(row.path)?.dirty"
                                          class="h-1.5 w-1.5 shrink-0 rounded-full bg-amber-400" style="display:none"
                                          title="Unsaved changes"></span>

                                    <div class="hidden shrink-0 items-center gap-0.5 group-hover:flex">
                                        <button type="button" x-on:click.stop="openNewDialog('file', row.path)"
                                                x-show="row.kind === 'dir'" title="New file here"
                                                class="grid h-5 w-5 place-items-center rounded text-[#8f8f8f]
                                                       hover:bg-black/[0.08] dark:hover:bg-white/[0.12]" style="display:none">
                                            <svg class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4">
                                                <path stroke-linecap="round" d="M12 5v14M5 12h14"/>
                                            </svg>
                                        </button>
                                        <button type="button" x-on:click.stop="openRenameDialog(row.path)"
                                                title="Rename"
                                                class="grid h-5 w-5 place-items-center rounded text-[#8f8f8f]
                                                       hover:bg-black/[0.08] dark:hover:bg-white/[0.12]">
                                            <svg class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                      d="m16.862 4.487 2.651 2.652M4.5 19.5l2.477-.025a4.5 4.5 0 0 0 1.13-1.897L18.36 6.74a1.875 1.875 0 0 1 2.652 2.652L9.08 18.51a4.5 4.5 0 0 1-1.897 1.13L4.5 19.5Z"/>
                                            </svg>
                                        </button>
                                        <button type="button" x-on:click.stop="removePath(row.path)"
                                                title="Delete"
                                                class="grid h-5 w-5 place-items-center rounded text-[#8f8f8f]
                                                       hover:bg-black/[0.08] dark:hover:bg-white/[0.12]">
                                            <svg class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" d="M6 6l12 12M18 6 6 18"/>
                                            </svg>
                                        </button>
                                    </div>
                                </div>
                            </template>
                        </div>

                        <p class="border-t border-black/[0.06] px-3 py-2 text-[10px] leading-relaxed text-[#8f8f8f]
                                  dark:border-white/[0.08]">
                            Drag a file onto a folder to move it. Right-click for more.
                        </p>
                    </div>
                </template>

                {{-- Search in files --}}
                <template x-if="panel === 'search'">
                    <div class="flex min-h-0 flex-1 flex-col">
                        <div class="flex items-center justify-between px-3 py-2.5">
                            <span class="text-xs font-semibold uppercase tracking-wider text-[#8f8f8f]">Search</span>
                            <span class="text-[10px] tabular-nums text-[#8f8f8f]"
                                  x-text="`${searchResults.length} result${searchResults.length === 1 ? '' : 's'}`"></span>
                        </div>

                        <div class="flex gap-1.5 px-2 pb-2">
                            <input
                                type="search"
                                x-model="searchQuery"
                                x-on:keydown.enter="runSearch()"
                                aria-label="Search in files"
                                placeholder="Search across the workspace"
                                class="w-full rounded-md border border-black/[0.08] bg-white px-2 py-1.5 text-xs
                                       placeholder:text-[#8f8f8f] focus:border-accent focus:outline-none
                                       dark:border-white/[0.12] dark:bg-[#242424]"
                            >
                            <button type="button" x-on:click="runSearch()"
                                    class="shrink-0 rounded-md bg-[#0d0d0d] px-2.5 text-xs font-medium text-white
                                           transition hover:bg-black/80 dark:bg-white dark:text-[#0d0d0d]">
                                Search
                            </button>
                        </div>

                        <div class="whale-scroll min-h-0 flex-1 overflow-y-auto px-1 pb-3">
                            <p x-show="!searchQuery" class="px-2 py-3 text-xs leading-relaxed text-[#8f8f8f]">
                                Search file contents. Results open at the matching line.
                            </p>
                            <p x-show="searchQuery && searchResults.length === 0 && !searching"
                               class="px-2 py-3 text-xs text-[#8f8f8f]" style="display:none">
                                No matches.
                            </p>

                            <template x-for="(match, index) in searchResults" :key="`${match.path}:${match.line}:${index}`">
                                <button
                                    type="button"
                                    x-on:click="openAtLine(match.path, match.line)"
                                    class="flex w-full flex-col gap-0.5 rounded-md px-2 py-1.5 text-left transition
                                           hover:bg-black/[0.05] dark:hover:bg-white/[0.06]"
                                >
                                    <span class="flex items-center gap-1.5 text-[11px] text-[#5d5d5d] dark:text-[#b4b4b4]">
                                        <span class="shrink-0 font-mono font-bold uppercase" :class="fileTone(match.path)"
                                              x-text="fileBadge(match.path)"></span>
                                        <span class="min-w-0 flex-1 truncate" x-text="match.path"></span>
                                        <span class="shrink-0 tabular-nums text-[#8f8f8f]" x-text="`:${match.line}`"></span>
                                    </span>
                                    <span class="truncate pl-4 font-mono text-[11px] text-[#8f8f8f]"
                                          x-text="match.excerpt"></span>
                                </button>
                            </template>
                        </div>
                    </div>
                </template>
            </aside>

            {{-- ───────────────────────── Main area ───────────────────────── --}}
            <main class="flex min-w-0 flex-1 flex-col">

                {{-- Tab bar --}}
                <div
                    x-show="tabs.length > 0"
                    class="whale-scroll flex h-9 shrink-0 items-stretch overflow-x-auto border-b
                           border-black/[0.08] bg-[#f9f9f9] dark:border-white/[0.12] dark:bg-[#181818]"
                    style="display:none"
                >
                    <template x-for="tab in tabs" :key="tab.path">
                        <div
                            x-on:click="selectTab(tab.path)"
                            x-on:auxclick.prevent="middleClick($event, tab.path)"
                            x-on:contextmenu.prevent.stop="openContextMenu($event, tab.path)"
                            class="group flex min-w-32 max-w-56 shrink-0 cursor-pointer items-center gap-2 border-r
                                   border-black/[0.08] px-3 text-xs transition dark:border-white/[0.12]"
                            :class="activePath === tab.path
                                ? 'bg-white text-[#0d0d0d] dark:bg-[#212121] dark:text-white'
                                : 'text-[#5d5d5d] hover:bg-black/[0.03] dark:text-[#b4b4b4] dark:hover:bg-white/[0.05]'"
                        >
                            <span class="shrink-0 font-mono font-bold uppercase" :class="fileTone(tab.path)"
                                  x-text="fileBadge(tab.path)"></span>
                            <span class="min-w-0 flex-1 truncate" x-text="tab.path.split('/').pop()"></span>

                            <button
                                type="button"
                                x-on:click.stop="closeTab(tab.path)"
                                :title="tab.dirty ? 'Close (unsaved)' : 'Close'"
                                class="grid h-4 w-4 shrink-0 place-items-center rounded-full text-[#8f8f8f] transition
                                       hover:bg-black/[0.1] dark:hover:bg-white/[0.15]"
                            >
                                <span x-show="tab.dirty" class="h-1.5 w-1.5 rounded-full bg-amber-400" style="display:none"></span>
                                <svg x-show="!tab.dirty" class="h-3 w-3" viewBox="0 0 24 24" fill="none"
                                     stroke="currentColor" stroke-width="2.4" style="display:none">
                                    <path stroke-linecap="round" d="M6 6l12 12M18 6 6 18"/>
                                </svg>
                            </button>
                        </div>
                    </template>
                </div>

                {{-- Breadcrumbs + editor actions --}}
                <div x-show="activePath" class="flex h-8 shrink-0 items-center gap-1 border-b border-black/[0.06]
                             px-3 text-[11px] text-[#8f8f8f] dark:border-white/[0.1]" style="display:none">
                    <template x-for="(crumb, index) in crumbs" :key="`${crumb.path}-${index}`">
                        <span class="flex items-center gap-1">
                            <span x-show="index > 0" class="opacity-50">/</span>
                            <button
                                type="button"
                                x-on:click="crumb.kind === 'dir' ? revealDir(crumb.path) : null"
                                class="rounded px-1 hover:bg-black/[0.05] hover:text-[#5d5d5d] dark:hover:bg-white/[0.08]"
                                x-text="crumb.name"
                            ></button>
                        </span>
                    </template>

                    <span class="ml-auto flex items-center gap-1">
                        <button type="button" x-on:click="toggleTerminal()"
                                class="rounded px-1.5 py-0.5 transition hover:bg-black/[0.05]
                                       dark:hover:bg-white/[0.08]"
                                title="Toggle terminal (Ctrl `)">Terminal</button>

                        <button type="button" x-on:click="copyEditor($event)"
                                class="rounded px-1.5 py-0.5 transition hover:bg-black/[0.05]
                                       dark:hover:bg-white/[0.08]">Copy</button>
                        <button type="button" x-on:click="save()" :disabled="!dirty"
                                class="rounded bg-[#0d0d0d] px-2 py-0.5 font-medium text-white transition
                                       hover:bg-black/80 disabled:cursor-not-allowed disabled:opacity-40
                                       dark:bg-white dark:text-[#0d0d0d] dark:hover:bg-[#ececec]">Save</button>
                    </span>
                </div>

                <div class="flex min-h-0 flex-1 flex-col lg:flex-row" :style="{ '--split': split + '%' }">

                    {{-- Code --}}
                    <section
                        x-show="layout !== 'preview'"
                        class="flex min-h-0 min-w-0 flex-1 basis-0 flex-col"
                    >
                        {{-- Find in file --}}
                        <div
                            x-show="findOpen"
                            class="flex shrink-0 items-center gap-1.5 border-b border-black/[0.08] bg-[#f9f9f9]
                                   px-3 py-1.5 dark:border-white/[0.12] dark:bg-[#181818]"
                            style="display:none"
                        >
                            <input
                                x-ref="findInput"
                                type="search"
                                x-model="findQuery"
                                x-on:input="findIndex = 0"
                                x-on:keydown.enter.prevent="jumpToMatch(1)"
                                x-on:keydown.escape.prevent="findOpen = false"
                                placeholder="Find"
                                class="w-56 rounded-md border border-black/[0.1] bg-white px-2 py-1 font-mono text-xs
                                       focus:border-accent focus:outline-none dark:border-white/[0.15] dark:bg-[#242424]"
                            >
                            <span class="text-[11px] tabular-nums text-[#8f8f8f]" x-text="findCounter()"></span>
                            <button type="button" x-on:click="jumpToMatch(-1)" title="Previous"
                                    class="grid h-6 w-6 place-items-center rounded text-[#8f8f8f] hover:bg-black/[0.06]
                                           dark:hover:bg-white/[0.1]">
                                <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 15.75 7.5-7.5 7.5 7.5"/>
                                </svg>
                            </button>
                            <button type="button" x-on:click="jumpToMatch(1)" title="Next"
                                    class="grid h-6 w-6 place-items-center rounded text-[#8f8f8f] hover:bg-black/[0.06]
                                           dark:hover:bg-white/[0.1]">
                                <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 8.25 7.5 7.5 7.5-7.5"/>
                                </svg>
                            </button>
                            <button type="button" x-on:click="findOpen = false" title="Close (Esc)"
                                    class="ml-auto grid h-6 w-6 place-items-center rounded text-[#8f8f8f]
                                           hover:bg-black/[0.06] dark:hover:bg-white/[0.1]">
                                <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
                                    <path stroke-linecap="round" d="M6 6l12 12M18 6 6 18"/>
                                </svg>
                            </button>
                        </div>

                        <div class="relative flex min-h-0 flex-1" x-show="activePath" style="display:none">
                            {{-- Line numbers --}}
                            <div
                                class="w-12 shrink-0 select-none overflow-hidden border-r border-black/[0.06]
                                       bg-white pt-4 text-right font-mono text-[13px] leading-[22px] text-[#b4b4b4]
                                       dark:border-white/[0.1] dark:bg-[#212121] dark:text-[#5d5d5d]"
                                aria-hidden="true"
                            >
                                <pre class="m-0 px-2 whitespace-pre" x-text="lineNumbers"></pre>
                            </div>

                            <textarea
                                x-ref="editor"
                                x-model="contents"
                                x-on:input="onEdit()"
                                x-on:scroll="syncGutter($event)"
                                x-on:keyup="syncCursor($event)"
                                x-on:click="syncCursor($event)"
                                x-on:keydown.tab.prevent="insertTab($event)"
                                x-on:keydown.escape="findOpen = false"
                                wrap="off"
                                spellcheck="false"
                                placeholder="Open a file, or write something new…"
                                class="whale-scroll min-h-0 flex-1 resize-none border-0 bg-white p-4 font-mono
                                       text-[13px] leading-[22px] text-[#0d0d0d] focus:outline-none
                                       dark:bg-[#212121] dark:text-[#ececec]"
                            ></textarea>
                        </div>

                        <div class="flex min-h-0 flex-1 flex-col items-center justify-center gap-3 p-8 text-center"
                             x-show="!activePath" style="display:none">
                            <p class="text-sm text-[#8f8f8f]">No file open</p>
                            <div class="flex flex-wrap justify-center gap-2">
                                <button type="button" x-on:click="openPalette('file')"
                                        class="rounded-lg border border-black/[0.1] px-3 py-1.5 text-xs font-medium
                                               transition hover:bg-black/[0.05] dark:border-white/[0.14]
                                               dark:hover:bg-white/[0.08]">Go to file (Ctrl P)</button>
                                <button type="button" x-on:click="showPanel('explorer')"
                                        class="rounded-lg border border-black/[0.1] px-3 py-1.5 text-xs font-medium
                                               transition hover:bg-black/[0.05] dark:border-white/[0.14]
                                               dark:hover:bg-white/[0.08]">Open explorer</button>                                <button type="button" x-on:click="toggleTerminal()"
                                        class="rounded-lg border border-black/[0.1] px-3 py-1.5 text-xs font-medium
                                               transition hover:bg-black/[0.05] dark:border-white/[0.14]
                                               dark:hover:bg-white/[0.08]">Terminal (Ctrl `)</button>

                            </div>
                        </div>
                    {{-- Terminal: allowlisted commands, confined to this workspace (Ctrl `) --}}
                    <div
                        x-show="terminalOpen"
                        style="display:none"
                        class="flex h-48 shrink-0 flex-col border-t border-black/[0.08] bg-[#101010] dark:border-white/[0.12]"
                    >
                        <div class="flex shrink-0 items-center gap-2 border-b border-white/[0.08] px-3 py-1.5 text-[11px]">
                            <span class="font-semibold uppercase tracking-wide text-[#8f8f8f]">Terminal</span>
                            <span class="min-w-0 flex-1 truncate font-mono text-[#8f8f8f]" x-text="'/' + terminalCwd"></span>
                            <button type="button" x-on:click="terminalLines = []"
                                    class="rounded px-1.5 py-0.5 transition hover:bg-white/[0.08]">Clear</button>
                            <button type="button" x-on:click="terminalOpen = false"
                                    class="rounded px-1.5 py-0.5 transition hover:bg-white/[0.08]"
                                    title="Close (Ctrl `)">✕</button>
                        </div>

                        <div x-ref="terminalOut"
                             class="whale-scroll min-h-0 flex-1 overflow-y-auto whitespace-pre-wrap px-3 py-2 font-mono text-[12px] leading-[18px]">
                            <p class="text-[#6b6b6b]" x-show="terminalLines.length === 0">Whale terminal — allowlisted commands only. Try: ls, mkdir docs, cat readme.md, grep whale ., wc index.html</p>
                            <template x-for="(line, index) in terminalLines" :key="index">
                                <div x-text="line.text"
                                     :class="line.kind === 'in' ? 'text-[#7ee787]' : line.kind === 'err' ? 'text-[#ff7b72]' : 'text-[#d4d4d4]'"></div>
                            </template>
                        </div>

                        <form x-on:submit.prevent="runTerminal()"
                              class="flex shrink-0 items-center gap-2 border-t border-white/[0.08] px-3 py-2">
                            <span class="shrink-0 font-mono text-[12px] text-[#7ee787]">$</span>
                            <input
                                x-ref="terminalInput"
                                x-model="terminalInput"
                                x-on:keydown="terminalKeys($event)"
                                autocomplete="off"
                                spellcheck="false"
                                placeholder="type a command… (↑ history, Ctrl ` closes)"
                                class="min-w-0 flex-1 bg-transparent font-mono text-[12px] text-[#ececec] focus:outline-none placeholder:text-[#6b6b6b]"
                            />
                        </form>
                    </div>

                    </section>

                    {{-- Resizer between code and preview --}}
                    <div
                        x-show="layout === 'split'"
                        x-on:pointerdown="startResize($event)"
                        class="h-1 w-full shrink-0 cursor-row-resize bg-black/[0.08] transition hover:bg-accent
                               lg:h-auto lg:w-1 lg:cursor-col-resize dark:bg-white/[0.12]"
                        style="display:none"
                        title="Drag to resize"
                    ></div>

                    {{-- Preview --}}
                    <section
                        x-show="layout !== 'code'"
                        class="flex min-h-[200px] shrink-0 basis-1/2 flex-col border-t border-black/[0.08]
                               lg:min-h-0 lg:basis-[var(--split)] lg:border-l lg:border-t-0 dark:border-white/[0.12]"
                        style="display:none"
                    >
                        <div class="flex h-9 shrink-0 items-center gap-2 border-b border-black/[0.08] px-3
                                    dark:border-white/[0.12]">
                            <div class="flex items-center gap-0.5 rounded-md bg-black/[0.05] p-0.5 dark:bg-white/[0.06]">
                                <template x-for="mode in [['code', 'Code'], ['split', 'Split'], ['preview', 'Preview']]" :key="mode[0]">
                                    <button
                                        type="button"
                                        x-on:click="setLayout(mode[0])"
                                        class="rounded px-2 py-0.5 text-[11px] font-medium transition focus-visible:outline-2
                                               focus-visible:outline-accent"
                                        :class="layout === mode[0]
                                            ? 'bg-white shadow-sm dark:bg-[#303030]'
                                            : 'text-[#8f8f8f] hover:text-[#5d5d5d]'"
                                        x-text="mode[1]"
                                    ></button>
                                </template>
                            </div>

                            <span class="min-w-0 flex-1 truncate font-mono text-[11px] text-[#8f8f8f]"
                                  x-text="activePath || ''"></span>

                            <button type="button" x-on:click="refreshPreview()" title="Reload"
                                    class="grid h-7 w-7 place-items-center rounded text-[#5d5d5d] transition
                                           hover:bg-black/[0.06] dark:text-[#b4b4b4] dark:hover:bg-white/[0.08]">
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                          d="M16.023 9.348h4.992V4.356M2.985 19.644v-4.992h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7"/>
                                </svg>
                            </button>
                            <a :href="previewUrl()" target="_blank" rel="noopener" title="Open in a new tab"
                               class="grid h-7 w-7 place-items-center rounded text-[#5d5d5d] transition
                                      hover:bg-black/[0.06] dark:text-[#b4b4b4] dark:hover:bg-white/[0.08]">
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                          d="M13.5 6H5.25A2.25 2.25 0 0 0 3 8.25v10.5A2.25 2.25 0 0 0 5.25 21h10.5A2.25 2.25 0 0 0 18 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25"/>
                                </svg>
                            </a>
                        </div>

                        <iframe
                            x-ref="preview"
                            x-show="previewable(activePath)"
                            :src="previewUrl()"
                            sandbox="allow-scripts allow-forms allow-popups allow-modals"
                            title="Workspace preview"
                            class="min-h-0 flex-1 border-0 bg-white dark:bg-[#181818]"
                            style="display:none"
                        ></iframe>

                        <div x-show="!previewable(activePath)"
                             class="flex min-h-0 flex-1 flex-col items-center justify-center gap-2 p-6 text-center">
                            <p class="text-sm text-[#8f8f8f]">This file has no live preview.</p>
                            <p class="text-xs text-[#b4b4b4]">
                                Previewable: HTML, SVG, images, video, PDF, CSS, JS, JSON and Markdown.
                            </p>
                            <a x-show="activePath" :href="previewUrl()" target="_blank" rel="noopener"
                               class="mt-1 rounded-lg border border-black/[0.1] px-3 py-1.5 text-xs font-medium
                                      transition hover:bg-black/[0.05] dark:border-white/[0.14]
                                      dark:hover:bg-white/[0.08]"
                               style="display:none">Open raw file</a>
                        </div>
                    </section>
                </div>

                {{-- ───────────────────────── Status bar ───────────────────────── --}}
                <footer class="flex h-6 shrink-0 items-center gap-3 border-t border-black/[0.08] bg-[#f9f9f9]
                               px-3 text-[11px] text-[#5d5d5d] dark:border-white/[0.12] dark:bg-[#181818]
                               dark:text-[#b4b4b4]">
                    <span class="tabular-nums" x-text="`Ln ${cursor.line}, Col ${cursor.col}`"></span>
                    <span class="hidden sm:inline">Spaces: 4</span>
                    <span class="hidden sm:inline">UTF-8</span>
                    <span class="hidden sm:inline">LF</span>
                    <span x-text="languageLabel(activePath)"></span>

                    <span class="ml-auto flex items-center gap-3">
                        <span x-show="dirty" class="flex items-center gap-1 text-amber-600 dark:text-amber-400"
                              style="display:none">
                            <span class="h-1.5 w-1.5 rounded-full bg-amber-400"></span> Unsaved
                        </span>
                        <span class="hidden sm:inline" x-text="`${files.length} files`"></span>
                        <span class="tabular-nums hidden sm:inline" x-text="`${contents.length} chars`"></span>
                        <span class="font-mono text-[10px] opacity-70" x-text="workspaceParam()"></span>
                    </span>
                </footer>
            </main>
        </div>

        {{-- ───────────────────────── Command palette ───────────────────────── --}}
        <div
            x-show="palette.open"
            x-transition:enter="transition ease-out duration-100"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            class="fixed inset-0 z-50 flex justify-center px-4 pt-[12vh]"
            style="display:none"
            x-on:click.self="closePalette()"
        >
            <div class="absolute inset-0 bg-black/40 backdrop-blur-[2px]" x-on:click="closePalette()"></div>

            <div class="relative flex w-full max-w-xl flex-col overflow-hidden rounded-xl border border-black/[0.08]
                        bg-white shadow-2xl dark:border-white/[0.14] dark:bg-[#252526]">
                <input
                    x-ref="paletteInput"
                    type="text"
                    x-model="palette.query"
                    x-on:input="palette.index = 0"
                    x-on:keydown.down.prevent="movePalette(1)"
                    x-on:keydown.up.prevent="movePalette(-1)"
                    x-on:keydown.enter.prevent="runPalette()"
                    x-on:keydown.escape.prevent="closePalette()"
                    :placeholder="palette.mode === 'file' ? 'Go to file…' : 'Type a command…'"
                    class="w-full border-b border-black/[0.08] bg-transparent px-4 py-3 text-sm
                           placeholder:text-[#8f8f8f] focus:outline-none dark:border-white/[0.12]"
                >

                <div class="whale-scroll max-h-80 overflow-y-auto p-1">
                    <template x-for="(item, index) in paletteItems()" :key="item.id">
                        <button
                            type="button"
                            x-on:click="runPaletteItem(item)"
                            x-on:mouseenter="palette.index = index"
                            class="flex w-full items-center gap-2.5 rounded-lg px-3 py-2 text-left text-sm transition"
                            :class="palette.index === index
                                ? 'bg-accent/10 text-[#0d0d0d] dark:text-white'
                                : 'hover:bg-black/[0.04] dark:hover:bg-white/[0.06]'"
                        >
                            <span class="shrink-0 font-mono text-[10px] font-bold uppercase"
                                  :class="item.kind === 'file' ? fileTone(item.id) : 'text-[#8f8f8f]'"
                                  x-text="item.kind === 'file' ? fileBadge(item.id) : '›'"></span>
                            <span class="min-w-0 flex-1 truncate" x-text="item.label"></span>
                            <span class="shrink-0 text-[11px] text-[#8f8f8f]" x-text="item.hint"></span>
                        </button>
                    </template>

                    <p x-show="paletteItems().length === 0" class="px-3 py-3 text-xs text-[#8f8f8f]">
                        Nothing matches.
                    </p>
                </div>
            </div>
        </div>

        {{-- ───────────────────────── Context menu ───────────────────────── --}}
        <div
            x-show="menu.open"
            x-on:click.window="menu.open = false"
            x-on:keydown.escape.window="menu.open = false"
            class="fixed z-50 w-48 overflow-hidden rounded-lg border border-black/[0.08] bg-white p-1 shadow-xl
                   dark:border-white/[0.14] dark:bg-[#252526]"
            :style="{ left: menu.x + 'px', top: menu.y + 'px' }"
            style="display:none"
        >
            <template x-for="item in menuItems()" :key="item.id">
                <button
                    type="button"
                    x-on:click="runMenuItem(item)"
                    class="flex w-full items-center gap-2 rounded-md px-2.5 py-1.5 text-left text-xs transition
                           hover:bg-black/[0.05] dark:hover:bg-white/[0.08]"
                >
                    <span class="w-4 text-center text-[#8f8f8f]" x-text="item.glyph"></span>
                    <span class="flex-1" x-text="item.label"></span>
                    <span class="text-[10px] text-[#8f8f8f]" x-text="item.hint"></span>
                </button>
            </template>
        </div>

        {{-- ───────────────────────── New / rename dialog ───────────────────────── --}}
        <div x-show="dialog.open" x-transition.opacity class="fixed inset-0 z-50 flex items-center justify-center p-4"
             style="display:none">
            <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" x-on:click="dialog.open = false"></div>
            <div class="relative w-full max-w-md rounded-2xl border border-black/[0.08] bg-white p-5 shadow-2xl
                        dark:border-white/[0.12] dark:bg-[#212121]">
                <h2 class="text-sm font-semibold" x-text="dialog.title"></h2>
                <p x-show="dialog.hint" x-text="dialog.hint" class="mt-1 text-xs text-[#8f8f8f]" style="display:none"></p>

                <input
                    x-ref="dialogInput"
                    type="text"
                    x-model="dialog.value"
                    x-on:keydown.enter.prevent="submitDialog()"
                    x-on:keydown.escape.prevent="dialog.open = false"
                    :placeholder="dialog.placeholder"
                    class="mt-4 w-full rounded-xl border border-black/[0.1] bg-white px-3 py-2.5 font-mono text-sm
                           focus:border-accent focus:outline-none focus:ring-2 focus:ring-accent/20
                           dark:border-white/[0.14] dark:bg-[#303030]"
                >

                <p x-show="dialog.error" x-text="dialog.error"
                   class="mt-2 text-xs text-rose-500" style="display:none"></p>

                <div class="mt-4 flex justify-end gap-2">
                    <button type="button" x-on:click="dialog.open = false"
                            class="rounded-lg px-3 py-1.5 text-xs font-medium text-[#5d5d5d] transition
                                   hover:bg-black/[0.05] dark:text-[#b4b4b4] dark:hover:bg-white/[0.08]">Cancel</button>
                    <button type="button" x-on:click="submitDialog()" :disabled="!dialog.value.trim()"
                            class="rounded-lg bg-[#0d0d0d] px-3 py-1.5 text-xs font-medium text-white transition
                                   hover:bg-black/80 disabled:cursor-not-allowed disabled:opacity-40
                                   dark:bg-white dark:text-[#0d0d0d]"
                            x-text="dialog.mode === 'rename' ? 'Rename' : 'Create'"></button>
                </div>
            </div>
        </div>

        {{-- ───────────────────────── Upload dialog ───────────────────────── --}}
        <div x-show="uploadDialogOpen" x-transition.opacity class="fixed inset-0 z-50 flex items-center justify-center p-4"
             style="display:none">
            <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" x-on:click="uploadDialogOpen = false"></div>
            <div class="relative w-full max-w-md rounded-2xl border border-black/[0.08] bg-white p-5 shadow-2xl
                        dark:border-white/[0.12] dark:bg-[#212121]">
                <h2 class="text-sm font-semibold">Upload files</h2>
                <p class="mt-1 text-xs text-[#8f8f8f]">Up to 10 files, 5 MB each. Drop them below or choose from disk.</p>
                <div
                    class="mt-4 flex flex-col items-center justify-center gap-2 rounded-xl border-2 border-dashed
                           border-black/[0.12] px-4 py-8 text-center dark:border-white/[0.16]"
                    x-on:dragover.prevent
                    x-on:drop.prevent="uploadFiles($event.dataTransfer.files)"
                >
                    <svg class="h-6 w-6 text-[#8f8f8f]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 16V4m0 0L8 8m4-4 4 4M4 16v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2"/>
                    </svg>
                    <span class="text-xs text-[#8f8f8f]">Drop files here</span>
                    <input type="file" multiple x-ref="filePicker" class="hidden"
                           x-on:change="uploadFiles($event.target.files); $event.target.value = ''">
                    <button type="button" x-on:click="$refs.filePicker.click()"
                            class="mt-1 rounded-lg bg-[#0d0d0d] px-3 py-1.5 text-xs font-medium text-white
                                   dark:bg-white dark:text-[#0d0d0d]">
                        Choose files
                    </button>
                </div>
                <p x-show="error" x-text="error" class="mt-3 text-xs text-rose-500" style="display:none"></p>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        function workspaceApp(routes) {
            // Extensions the server will serve inline (Workspace::PREVIEWABLE_EXTENSIONS).
            const PREVIEWABLE = ['html', 'htm', 'svg', 'png', 'jpg', 'jpeg', 'gif', 'webp', 'mp4', 'webm', 'pdf', 'txt', 'md', 'css', 'js', 'json'];

            const TONES = {
                html: 'text-orange-500', htm: 'text-orange-500',
                css: 'text-sky-500', scss: 'text-sky-500', less: 'text-sky-500',
                js: 'text-amber-400', mjs: 'text-amber-400', cjs: 'text-amber-400',
                ts: 'text-blue-500', tsx: 'text-blue-500', jsx: 'text-blue-500',
                json: 'text-emerald-500', yml: 'text-emerald-500', yaml: 'text-emerald-500',
                php: 'text-indigo-400', blade: 'text-indigo-400',
                md: 'text-[#8f8f8f]', markdown: 'text-[#8f8f8f]',
                svg: 'text-pink-500', png: 'text-pink-500', jpg: 'text-pink-500',
                jpeg: 'text-pink-500', gif: 'text-pink-500', webp: 'text-pink-500',
                mp4: 'text-purple-500', webm: 'text-purple-500', pdf: 'text-rose-500',
                py: 'text-green-500', rb: 'text-rose-500', go: 'text-cyan-400',
                rs: 'text-orange-600', java: 'text-red-500', sql: 'text-teal-500',
                sh: 'text-lime-500', bash: 'text-lime-500', csv: 'text-lime-600',
            };

            return {
                routes,
                files: [],
                directories: [],
                tabs: [],
                activePath: null,
                contents: '',
                dirty: false,
                cursor: { line: 1, col: 1 },
                draggedPath: null,
                dragOverPath: null,
                treeDragOver: false,
                treeFilter: '',
                expanded: [],
                panel: 'explorer',
                sidebarOpen: true,
                layout: 'split',
                split: 50,
                findOpen: false,
                findQuery: '',
                findIndex: 0,
                searchQuery: '',
                searchResults: [],
                searching: false,
                palette: { open: false, mode: 'file', query: '', index: 0 },
                menu: { open: false, x: 0, y: 0, path: null },
                dialog: { open: false, mode: 'file', title: '', hint: '', placeholder: '', value: '', target: null, error: '' },
                uploadDialogOpen: false,
                error: null,                terminalOpen: false,
                terminalLines: [],
                terminalInput: '',
                terminalHistory: [],
                terminalHistoryIndex: -1,
                terminalCwd: '',

                _resize: null,

                init() {
                    this.refresh();
                    window.addEventListener('keydown', (event) => this.onKeydown(event));
                    window.addEventListener('resize', () => { this.menu.open = false; });

                    // Losing unsaved work to a refresh is the one thing an editor
                    // must never do silently.
                    window.addEventListener('beforeunload', (event) => {
                        if (this.hasDirty()) {
                            event.preventDefault();
                            event.returnValue = '';
                        }
                    });
                },

                headers() {
                    return { 'Accept': 'application/json' };
                },

                workspaceParam() {
                    return window.whaleWorkspaceId();
                },

                // ---- Files ---------------------------------------------------
                async refresh() {
                    try {
                        const url = new URL(this.routes.list, window.location.origin);
                        url.searchParams.set('workspace', this.workspaceParam());

                        const response = await fetch(url, { headers: this.headers() });
                        const payload = await response.json().catch(() => null);

                        this.files = payload?.files ?? [];
                        this.directories = payload?.directories ?? [];
                    } catch (error) {
                        this.error = 'Could not load the workspace.';
                    }
                },

                applyPayload(payload) {
                    if (payload?.files) {
                        this.files = payload.files;
                    }
                    if (payload?.directories) {
                        this.directories = payload.directories;
                    }
                },

                ext(path) {
                    const name = String(path ?? '').split('/').pop() ?? '';
                    const dot = name.lastIndexOf('.');
                    return dot > 0 ? name.slice(dot + 1).toLowerCase() : '';
                },

                fileBadge(path) {
                    const name = String(path ?? '').split('/').pop() ?? '';
                    if (name.startsWith('.') && !name.slice(1).includes('.')) {
                        return name.slice(1).slice(0, 3);
                    }
                    const extension = this.ext(path);
                    return extension ? extension.slice(0, 3) : 'txt';
                },

                fileTone(path) {
                    const name = String(path ?? '').split('/').pop() ?? '';
                    if (name.startsWith('blade')) {
                        return 'text-indigo-400';
                    }
                    return TONES[this.ext(path)] ?? 'text-[#8f8f8f]';
                },

                languageLabel(path) {
                    if (!path) {
                        return 'Plain Text';
                    }
                    const map = {
                        js: 'JavaScript', mjs: 'JavaScript', cjs: 'JavaScript',
                        ts: 'TypeScript', tsx: 'TypeScript', jsx: 'JavaScript',
                        html: 'HTML', htm: 'HTML', css: 'CSS', scss: 'SCSS',
                        json: 'JSON', md: 'Markdown', php: 'PHP', py: 'Python',
                        rb: 'Ruby', go: 'Go', rs: 'Rust', sql: 'SQL', sh: 'Shell',
                        yml: 'YAML', yaml: 'YAML', svg: 'SVG', xml: 'XML',
                    };
                    return map[this.ext(path)] ?? this.ext(path).toUpperCase() ?? 'Plain Text';
                },

                previewable(path) {
                    if (!path) {
                        return false;
                    }
                    return PREVIEWABLE.includes(this.ext(path));
                },

                // ---- Tree ----------------------------------------------------
                buildTree() {
                    const root = { name: '', path: '', kind: 'dir', children: [] };
                    const index = new Map([['', root]]);

                    const ensureDir = (path) => {
                        if (index.has(path)) {
                            return index.get(path);
                        }

                        const slash = path.lastIndexOf('/');
                        const parent = ensureDir(slash === -1 ? '' : path.slice(0, slash));
                        const node = { name: path.slice(slash + 1), path, kind: 'dir', children: [] };

                        parent.children.push(node);
                        index.set(path, node);

                        return node;
                    };

                    for (const directory of this.directories) {
                        ensureDir(String(directory).replace(/\/+$/, ''));
                    }

                    for (const file of this.files) {
                        const slash = file.path.lastIndexOf('/');
                        const parent = ensureDir(slash === -1 ? '' : file.path.slice(0, slash));

                        parent.children.push({
                            name: file.path.slice(slash + 1),
                            path: file.path,
                            kind: 'file',
                            size: file.size ?? 0,
                            binary: file.binary === true,
                        });
                    }

                    const sort = (node) => {
                        node.children.sort((a, b) => {
                            if (a.kind !== b.kind) {
                                return a.kind === 'dir' ? -1 : 1;
                            }
                            return a.name.localeCompare(b.name, undefined, { numeric: true });
                        });
                        node.children.forEach((child) => { if (child.kind === 'dir') sort(child); });
                    };

                    sort(root);

                    return root;
                },

                get tree() {
                    return this.buildTree();
                },

                subtreeMatches(node) {
                    const needle = this.treeFilter.toLowerCase();

                    if (!needle) {
                        return true;
                    }

                    if (node.path.toLowerCase().includes(needle)) {
                        return true;
                    }

                    return node.children.some((child) => child.kind === 'dir'
                        ? this.subtreeMatches(child) || child.path.toLowerCase().includes(needle)
                        : child.path.toLowerCase().includes(needle));
                },

                // Flattened rows so one x-for can render the whole tree with
                // the right indent depth for every level.
                get explorerRows() {
                    const rows = [];
                    const needle = this.treeFilter.trim().toLowerCase();

                    const walk = (node, depth) => {
                        for (const child of node.children) {
                            if (needle) {
                                if (child.kind === 'dir') {
                                    if (!this.subtreeMatches(child)) {
                                        continue;
                                    }
                                    rows.push({ ...child, depth, expanded: true });
                                    walk(child, depth + 1);
                                } else if (child.path.toLowerCase().includes(needle)) {
                                    rows.push({ ...child, depth });
                                }

                                continue;
                            }

                            if (child.kind === 'dir') {
                                const expanded = this.isExpanded(child.path);
                                rows.push({ ...child, depth, expanded });
                                if (expanded) {
                                    walk(child, depth + 1);
                                }
                            } else {
                                rows.push({ ...child, depth });
                            }
                        }
                    };

                    walk(this.tree, 0);

                    return rows;
                },

                isExpanded(path) {
                    return this.expanded.includes(path);
                },

                toggleDir(path) {
                    if (this.isExpanded(path)) {
                        this.expanded = this.expanded.filter((entry) => entry !== path);
                    } else {
                        this.expanded = [...this.expanded, path];
                    }
                },

                collapseAll() {
                    this.expanded = [];
                },

                expandAll() {
                    this.expanded = this.directories.slice();
                },

                // Expand a folder and scroll it into view (from the breadcrumb).
                revealDir(path) {
                    if (!this.expanded.includes(path)) {
                        this.expanded = [...this.expanded, path];
                    }
                    this.showPanel('explorer');
                },

                get crumbs() {
                    if (!this.activePath) {
                        return [];
                    }
                    const parts = this.activePath.split('/');
                    return parts.map((name, index) => ({
                        name,
                        kind: index === parts.length - 1 ? 'file' : 'dir',
                        path: parts.slice(0, index + 1).join('/'),
                    }));
                },

                // ---- Tabs ----------------------------------------------------
                tabFor(path) {
                    return this.tabs.find((tab) => tab.path === path) ?? null;
                },

                hasDirty() {
                    return this.tabs.some((tab) => tab.dirty);
                },
                async open(path, line = null) {
                    const existing = this.tabFor(path);

                    if (existing) {
                        this.activePath = path;
                        this.contents = existing.contents;
                        this.dirty = existing.dirty;
                        this.cursor = { line: 1, col: 1 };
                        this.$nextTick(() => this.refreshPreview());
                        return;
                    }

                    this.error = null;

                    try {
                        const url = new URL(this.routes.read, window.location.origin);
                        url.searchParams.set('path', path);
                        url.searchParams.set('workspace', this.workspaceParam());

                        const response = await fetch(url, { headers: this.headers() });
                        const payload = await response.json().catch(() => null);

                        if (!response.ok) {
                            throw new Error(payload?.message || 'Could not open that file.');
                        }

                        this.tabs = [...this.tabs, { path, contents: payload?.contents ?? '', dirty: false }];
                        this.activePath = path;
                        this.contents = payload?.contents ?? '';
                        this.dirty = false;
                        this.cursor = { line: 1, col: 1 };
                        this.$nextTick(() => this.refreshPreview());

                        if (line) {
                            this.$nextTick(() => this.goToLine(line));
                        }
                    } catch (error) {
                        this.error = error.message;
                    }
                },

                openAtLine(path, line) {
                    this.open(path, line);
                    if (window.innerWidth < 1024) {
                        this.sidebarOpen = false;
                    }
                },

                selectTab(path) {
                    if (this.activePath === path) {
                        return;
                    }
                    const tab = this.tabFor(path);

                    if (!tab) {
                        this.open(path);
                        return;
                    }

                    this.activePath = path;
                    this.contents = tab.contents;
                    this.dirty = tab.dirty;
                    this.cursor = { line: 1, col: 1 };
                    this.$nextTick(() => this.refreshPreview());
                },

                closeTab(path) {
                    const tab = this.tabFor(path);
                    if (!tab) {
                        return;
                    }

                    if (tab.dirty && !window.confirm(`"${path}" has unsaved changes. Close anyway?`)) {
                        return;
                    }

                    const index = this.tabs.findIndex((entry) => entry.path === path);
                    this.tabs = this.tabs.filter((entry) => entry.path !== path);

                    if (this.activePath === path) {
                        const next = this.tabs[index] ?? this.tabs[index - 1] ?? null;
                        this.activePath = next ? next.path : null;
                        this.contents = next ? next.contents : '';
                        this.dirty = next ? next.dirty : false;
                        this.$nextTick(() => this.refreshPreview());
                    }
                },

                // Alpine has no mouse-button modifier, so a middle click has
                // to be recognised by hand — it means "close this tab".
                middleClick(event, path) {
                    if (event.button === 1) {
                        this.closeTab(path);
                    }
                },

                onEdit() {
                    const tab = this.tabFor(this.activePath);

                    if (tab) {
                        tab.contents = this.contents;
                        tab.dirty = true;
                    }

                    this.dirty = true;
                    this.syncCursor();
                },

                async save() {
                    const tab = this.tabFor(this.activePath);

                    if (!tab) {
                        return;
                    }

                    try {
                        const response = await fetch(this.routes.write, {
                            method: 'PUT',
                            headers: { ...this.headers(), 'Content-Type': 'application/json' },
                            body: JSON.stringify({
                                path: this.activePath,
                                contents: this.contents,
                                workspace: this.workspaceParam(),
                            }),
                        });

                        const payload = await response.json().catch(() => null);

                        if (!response.ok) {
                            throw new Error(payload?.message || 'Could not save the file.');
                        }

                        this.applyPayload(payload);
                        tab.contents = this.contents;
                        tab.dirty = false;
                        this.dirty = false;
                        this.refreshPreview();
                    } catch (error) {
                        this.error = error.message;
                    }
                },

                async removePath(path) {
                    const isDir = this.directories.includes(path);
                    const label = isDir ? 'folder and everything in it' : 'file';

                    if (!window.confirm(`Delete this ${label}: ${path}?`)) {
                        return;
                    }

                    const url = new URL(this.routes.remove, window.location.origin);
                    url.searchParams.set('path', path);
                    url.searchParams.set('workspace', this.workspaceParam());

                    try {
                        const response = await fetch(url, { method: 'DELETE', headers: this.headers() });
                        const payload = await response.json().catch(() => null);

                        if (!response.ok) {
                            throw new Error(payload?.message || 'Could not delete.');
                        }

                        this.applyPayload(payload);

                        const affected = this.tabs
                            .map((tab) => tab.path)
                            .filter((tabPath) => tabPath === path || tabPath.startsWith(`${path}/`));

                        affected.forEach((tabPath) => {
                            this.tabs = this.tabs.filter((tab) => tab.path !== tabPath);

                            if (this.activePath === tabPath) {
                                this.activePath = null;
                                this.contents = '';
                                this.dirty = false;
                            }
                        });

                        this.expanded = this.expanded.filter((entry) => entry !== path && !entry.startsWith(`${path}/`));
                    } catch (error) {
                        this.error = error.message;
                    }
                },

                async moveFile(from, to) {
                    if (!from || !to || from === to) {
                        return;
                    }

                    const response = await fetch(this.routes.move, {
                        method: 'POST',
                        headers: { ...this.headers(), 'Content-Type': 'application/json' },
                        body: JSON.stringify({ from, to, workspace: this.workspaceParam() }),
                    });

                    const payload = await response.json().catch(() => null);

                    if (!response.ok) {
                        this.error = payload?.message || 'Could not move the file.';
                        return;
                    }

                    this.applyPayload(payload);

                    // Keep open tabs pointing at their new home.
                    this.tabs = this.tabs.map((tab) => tab.path === from ? { ...tab, path: to } : tab);
                    if (this.activePath === from) {
                        this.activePath = to;
                    }
                },

                // Dropping a tree item onto another moves it; dropping real
                // files from the OS uploads them instead.
                handleDrop(event) {
                    this.treeDragOver = false;
                    this.dragOverPath = null;

                    if (event.dataTransfer.files.length > 0) {
                        this.uploadFiles(event.dataTransfer.files);
                        return;
                    }

                    if (!this.draggedPath) {
                        return;
                    }

                    const target = event.target.closest('[data-path]');

                    if (!target) {
                        return;
                    }

                    const targetPath = String(target.dataset.path ?? '');
                    const targetKind = String(target.dataset.kind ?? 'file');
                    const folder = targetKind === 'dir'
                        ? targetPath
                        : (targetPath.includes('/') ? targetPath.slice(0, targetPath.lastIndexOf('/')) : '');

                    if (!folder || this.draggedPath === folder) {
                        return;
                    }

                    const base = this.draggedPath.split('/').pop();
                    this.moveFile(this.draggedPath, `${folder}/${base}`);
                },

                // ---- New / rename -------------------------------------------
                openNewDialog(mode, target = null) {
                    this.dialog = {
                        open: true,
                        mode,
                        title: mode === 'folder' ? 'New folder' : 'New file',
                        hint: target ? `Inside ${target}/` : 'Relative to the workspace root.',
                        placeholder: mode === 'folder' ? 'components' : 'index.html',
                        value: target ? `${target}/` : '',
                        target,
                        error: '',
                    };
                    this.$nextTick(() => { this.$refs.dialogInput?.focus(); this.$refs.dialogInput?.select(); });
                },

                openRenameDialog(path) {
                    this.dialog = {
                        open: true,
                        mode: 'rename',
                        title: 'Rename',
                        hint: path,
                        placeholder: path,
                        value: path,
                        target: path,
                        error: '',
                    };
                    this.$nextTick(() => { this.$refs.dialogInput?.focus(); this.$refs.dialogInput?.select(); });
                },

                async submitDialog() {
                    const value = this.dialog.value.trim();

                    if (!value) {
                        return;
                    }

                    if (this.dialog.mode === 'rename') {
                        await this.moveFile(this.dialog.target, value);
                        if (!this.error) {
                            this.dialog.open = false;
                        }
                        return;
                    }

                    if (this.dialog.mode === 'folder') {
                        try {
                            const response = await fetch(this.routes.mkdir, {
                                method: 'POST',
                                headers: { ...this.headers(), 'Content-Type': 'application/json' },
                                body: JSON.stringify({ path: value, workspace: this.workspaceParam() }),
                            });

                            const payload = await response.json().catch(() => null);

                            if (!response.ok) {
                                throw new Error(payload?.message || 'Could not create the folder.');
                            }

                            this.applyPayload(payload);
                            if (!this.expanded.includes(value)) {
                                this.expanded = [...this.expanded, value];
                            }
                            this.dialog.open = false;
                        } catch (error) {
                            this.dialog.error = error.message;
                        }

                        return;
                    }

                    // A new file is opened straight away, empty and unsaved, so
                    // the editor is ready for the first keystroke.
                    this.dialog.open = false;
                    if (this.tabFor(value)) {
                        this.selectTab(value);
                        return;
                    }
                    this.tabs = [...this.tabs, { path: value, contents: '', dirty: true }];
                    this.activePath = value;
                    this.contents = '';
                    this.dirty = true;
                    this.$nextTick(() => this.$refs.editor?.focus());
                },

                // ---- Dragging the split -------------------------------------
                startResize(event) {
                    const container = event.currentTarget.parentElement;
                    this._resize = (move) => {
                        const rect = container.getBoundingClientRect();
                        const vertical = window.innerWidth >= 1024;
                        const ratio = vertical
                            ? ((move.clientX - rect.left) / rect.width) * 100
                            : ((move.clientY - rect.top) / rect.height) * 100;
                        this.split = Math.min(75, Math.max(25, Math.round(ratio)));
                    };

                    const onMove = (move) => this._resize(move);
                    const onUp = () => {
                        window.removeEventListener('pointermove', onMove);
                        window.removeEventListener('pointerup', onUp);
                        this._resize = null;
                    };

                    window.addEventListener('pointermove', onMove);
                    window.addEventListener('pointerup', onUp);
                },

                setLayout(mode) {
                    this.layout = mode;
                    if (mode !== 'code') {
                        this.$nextTick(() => this.refreshPreview());
                    }
                },

                // ---- Editor --------------------------------------------------
                get lineNumbers() {
                    const count = Math.max(1, (this.contents || '').split('\n').length);
                    const numbers = [];
                    for (let i = 1; i <= count; i++) {
                        numbers.push(String(i));
                    }
                    return numbers.join('\n');
                },

                syncGutter(event) {
                    const pre = this.$refs.editor?.previousElementSibling?.querySelector('pre');
                    if (pre) {
                        pre.style.transform = `translateY(${-event.target.scrollTop}px)`;
                    }
                },

                syncCursor(event) {
                    const textarea = event?.target ?? this.$refs.editor;

                    if (!textarea || typeof textarea.selectionStart !== 'number') {
                        return;
                    }

                    const before = (textarea.value || '').slice(0, textarea.selectionStart);
                    const lines = before.split('\n');

                    this.cursor = { line: lines.length, col: lines[lines.length - 1].length + 1 };
                },

                goToLine(line) {
                    const textarea = this.$refs.editor;

                    if (!textarea) {
                        return;
                    }

                    const lines = textarea.value.split('\n');
                    const target = Math.min(Math.max(1, line), lines.length);
                    let position = 0;

                    for (let i = 0; i < target - 1; i++) {
                        position += lines[i].length + 1;
                    }

                    textarea.focus();
                    textarea.setSelectionRange(position, position);
                    textarea.scrollTop = Math.max(0, (target - 1) * 22 - textarea.clientHeight / 2);
                    this.syncGutter({ target: textarea });
                    this.syncCursor({ target: textarea });
                },

                insertTab(event) {
                    const textarea = event.target;
                    const start = textarea.selectionStart;
                    const end = textarea.selectionEnd;

                    textarea.value = `${textarea.value.slice(0, start)}    ${textarea.value.slice(end)}`;
                    textarea.selectionStart = textarea.selectionEnd = start + 4;
                    this.contents = textarea.value;
                    this.onEdit();
                },

                // ---- Find in file -------------------------------------------
                get findMatches() {
                    const needle = this.findQuery;

                    if (!needle) {
                        return [];
                    }

                    const haystack = this.contents.toLowerCase();
                    const target = needle.toLowerCase();
                    const found = [];
                    let index = haystack.indexOf(target);

                    while (index !== -1 && found.length < 500) {
                        found.push(index);
                        index = haystack.indexOf(target, index + Math.max(1, target.length));
                    }

                    return found;
                },

                findCounter() {
                    if (!this.findQuery) {
                        return '';
                    }
                    const total = this.findMatches.length;
                    if (total === 0) {
                        return 'No results';
                    }
                    return `${(this.findIndex % total) + 1} of ${total}`;
                },

                jumpToMatch(delta) {
                    const matches = this.findMatches;

                    if (matches.length === 0) {
                        return;
                    }

                    this.findIndex = (this.findIndex + delta + matches.length) % matches.length;

                    const start = matches[this.findIndex];
                    const textarea = this.$refs.editor;

                    if (!textarea) {
                        return;
                    }

                    textarea.focus();
                    textarea.setSelectionRange(start, start + this.findQuery.length);

                    const line = this.contents.slice(0, start).split('\n').length;
                    textarea.scrollTop = Math.max(0, (line - 1) * 22 - textarea.clientHeight / 2);
                    this.syncGutter({ target: textarea });
                    this.syncCursor({ target: textarea });
                },

                // ---- Search in files ----------------------------------------
                async runSearch() {
                    const query = this.searchQuery.trim();

                    if (!query) {
                        this.searchResults = [];
                        return;
                    }

                    this.searching = true;

                    try {
                        const url = new URL(this.routes.search, window.location.origin);
                        url.searchParams.set('q', query);
                        url.searchParams.set('workspace', this.workspaceParam());

                        const response = await fetch(url, { headers: this.headers() });
                        const payload = await response.json().catch(() => null);

                        this.searchResults = payload?.matches ?? [];
                    } catch (error) {
                        this.error = 'Search failed.';
                    } finally {
                        this.searching = false;
                    }
                },

                // ---- Command palette ----------------------------------------
                showPanel(name) {
                    if (this.panel === name && this.sidebarOpen) {
                        this.sidebarOpen = false;
                        return;
                    }
                    this.panel = name;
                    this.sidebarOpen = true;
                },

                openPalette(mode) {
                    this.palette = { open: true, mode, query: '', index: 0 };
                    this.$nextTick(() => this.$refs.paletteInput?.focus());
                },

                closePalette() {
                    this.palette.open = false;
                },

                movePalette(delta) {
                    const items = this.paletteItems();
                    if (items.length === 0) {
                        return;
                    }
                    this.palette.index = (this.palette.index + delta + items.length) % items.length;
                },

                runPalette() {
                    const items = this.paletteItems();
                    const item = items[this.palette.index] ?? items[0];
                    if (item) {
                        this.runPaletteItem(item);
                    }
                },

                paletteItems() {
                    const query = this.palette.query.trim().toLowerCase();

                    if (this.palette.mode === 'file') {
                        return this.files
                            .filter((file) => !query || file.path.toLowerCase().includes(query))
                            .slice(0, 40)
                            .map((file) => ({ id: file.path, label: file.path, hint: 'file', kind: 'file' }));
                    }

                    return this.commandList()
                        .filter((command) => !query
                            || command.label.toLowerCase().includes(query)
                            || command.id.includes(query))
                        .slice(0, 30);
                },

                commandList() {
                    return [
                        { id: 'file', label: 'New file', hint: '', kind: 'command', run: () => this.openNewDialog('file') },
                        { id: 'folder', label: 'New folder', hint: '', kind: 'command', run: () => this.openNewDialog('folder') },
                        { id: 'save', label: 'File: Save', hint: 'Ctrl S', kind: 'command', run: () => this.save() },
                        { id: 'close', label: 'View: Close editor', hint: '', kind: 'command', run: () => this.activePath && this.closeTab(this.activePath) },
                        { id: 'explorer', label: 'View: Show explorer', hint: 'Ctrl B', kind: 'command', run: () => this.showPanel('explorer') },
                        { id: 'search', label: 'View: Search in files', hint: 'Ctrl Shift F', kind: 'command', run: () => this.showPanel('search') },
                        { id: 'find', label: 'Find in current file', hint: 'Ctrl F', kind: 'command', run: () => { this.findOpen = true; this.$nextTick(() => this.$refs.findInput?.focus()); } },
                        { id: 'code', label: 'Layout: Editor only', hint: '', kind: 'command', run: () => this.setLayout('code') },
                        { id: 'split', label: 'Layout: Split editor and preview', hint: '', kind: 'command', run: () => this.setLayout('split') },
                        { id: 'preview', label: 'Layout: Preview only', hint: '', kind: 'command', run: () => this.setLayout('preview') },
                        { id: 'collapse', label: 'Explorer: Collapse all folders', hint: '', kind: 'command', run: () => this.collapseAll() },
                        { id: 'expand', label: 'Explorer: Expand all folders', hint: '', kind: 'command', run: () => this.expandAll() },
                        { id: 'refresh', label: 'Explorer: Refresh', hint: '', kind: 'command', run: () => this.refresh() },
                        { id: 'upload', label: 'Upload files', hint: '', kind: 'command', run: () => { this.uploadDialogOpen = true; } },
                        { id: 'export', label: 'Export workspace as zip', hint: '', kind: 'command', run: () => { window.location.href = this.exportUrl(); } },
                        { id: 'studio', label: 'Open Studio', hint: '', kind: 'command', run: () => { window.location.href = this.routes.studio; } },
                        { id: 'chat', label: 'Open chat', hint: '', kind: 'command', run: () => { window.location.href = this.routes.chat; } },
                    ];
                },

                runPaletteItem(item) {
                    if (item.kind === 'file') {
                        this.closePalette();
                        this.open(item.id);
                        return;
                    }
                    this.closePalette();
                    item.run();
                },

                // ---- Context menu -------------------------------------------
                openContextMenu(event, path) {
                    const width = 192;
                    const height = 168;
                    const x = Math.min(event.clientX, window.innerWidth - width - 8);
                    const y = Math.min(event.clientY, window.innerHeight - height - 8);

                    this.menu = { open: true, x: Math.max(8, x), y: Math.max(8, y), path };
                },

                menuItems() {
                    const path = this.menu.path;
                    const isDir = path ? this.directories.includes(path) : false;
                    const isFile = path ? !isDir : false;

                    if (isDir) {
                        return [
                            { id: 'new-file', glyph: '+', label: 'New file here', hint: '', run: () => this.openNewDialog('file', path) },
                            { id: 'new-folder', glyph: '⊞', label: 'New folder here', hint: '', run: () => this.openNewDialog('folder', path) },
                            { id: 'rename', glyph: '✎', label: 'Rename', hint: '', run: () => this.openRenameDialog(path) },
                            { id: 'delete', glyph: '×', label: 'Delete', hint: '', run: () => this.removePath(path) },
                        ];
                    }

                    if (isFile) {
                        return [
                            { id: 'open', glyph: '↗', label: 'Open', hint: '', run: () => this.open(path) },
                            { id: 'rename', glyph: '✎', label: 'Rename', hint: '', run: () => this.openRenameDialog(path) },
                            { id: 'delete', glyph: '×', label: 'Delete', hint: '', run: () => this.removePath(path) },
                        ];
                    }

                    return [
                        { id: 'new-file', glyph: '+', label: 'New file', hint: '', run: () => this.openNewDialog('file') },
                        { id: 'new-folder', glyph: '⊞', label: 'New folder', hint: '', run: () => this.openNewDialog('folder') },
                        { id: 'refresh', glyph: '⟳', label: 'Refresh', hint: '', run: () => this.refresh() },
                        { id: 'upload', glyph: '↑', label: 'Upload files', hint: '', run: () => { this.uploadDialogOpen = true; } },
                    ];
                },

                runMenuItem(item) {
                    this.menu.open = false;
                    item.run();
                },

                // ---- Keyboard ------------------------------------------------
                onKeydown(event) {
                    const mod = event.ctrlKey || event.metaKey;
                    const key = event.key.toLowerCase();

                    if (event.key === 'Escape') {
                        this.menu.open = false;
                        if (this.palette.open) {
                            this.palette.open = false;
                        }
                        if (this.findOpen) {
                            this.findOpen = false;
                        }
                        return;
                    }

                    if (!mod) {
                        return;
                    }

                    // Shift combinations have to be checked first: "Shift+P"
                    // would otherwise be swallowed by the plain "p" branch.
                    if (event.shiftKey && key === 'p') {
                        event.preventDefault();
                        this.openPalette('command');
                        return;
                    }

                    if (event.shiftKey && key === 'f') {
                        event.preventDefault();
                        this.showPanel('search');
                        return;
                    }

                    if (event.shiftKey && key === 'e') {
                        event.preventDefault();
                        this.showPanel('explorer');
                        return;
                    }

                    if (event.code === 'Backquote') {
                        event.preventDefault();
                        this.toggleTerminal();
                        return;
                    }

                    if (key === 'p') {
                        event.preventDefault();
                        this.openPalette('file');
                        return;
                    }

                    if (key === 's') {
                        event.preventDefault();
                        this.save();
                        return;
                    }

                    if (key === 'b') {
                        event.preventDefault();
                        this.sidebarOpen = !this.sidebarOpen;
                        return;
                    }

                    if (key === 'f' && this.activePath) {
                        event.preventDefault();
                        this.findOpen = true;
                        this.$nextTick(() => this.$refs.findInput?.focus());
                    }
                },

                // ---- Preview -------------------------------------------------
                previewUrl() {
                    if (!this.activePath) {
                        return 'about:blank';
                    }

                    const url = new URL(this.routes.preview, window.location.origin);
                    url.searchParams.set('path', this.activePath);
                    url.searchParams.set('workspace', this.workspaceParam());

                    return url.toString();
                },

                exportUrl() {
                    const url = new URL(this.routes.export, window.location.origin);
                    url.searchParams.set('workspace', this.workspaceParam());

                    return url.toString();
                },

                refreshPreview() {
                    const frame = this.$refs.preview;

                    if (frame) {
                        frame.src = this.previewUrl();
                    }
                },

                // ---- Upload -------------------------------------------------
                async uploadFiles(fileList) {
                    const files = Array.from(fileList ?? []);

                    if (files.length === 0) {
                        return;
                    }

                    const body = new FormData();
                    body.append('workspace', this.workspaceParam());
                    files.forEach((file) => body.append('files[]', file));

                    try {
                        const response = await fetch(this.routes.upload, {
                            method: 'POST',
                            headers: this.headers(),
                            body,
                        });

                        const payload = await response.json().catch(() => null);

                        if (!response.ok) {
                            throw new Error(payload?.message || 'Upload failed.');
                        }

                        this.applyPayload(payload);
                        this.uploadDialogOpen = false;
                        this.error = null;
                    } catch (error) {
                        this.error = error.message;
                    }
                },

                // ---- Terminal ---------------------------------------------
                toggleTerminal() {
                    this.terminalOpen = !this.terminalOpen;

                    if (this.terminalOpen) {
                        this.$nextTick(() => this.$refs.terminalInput?.focus());
                    }
                },
                terminalKeys(event) {
                    if (event.key === 'ArrowUp') {
                        event.preventDefault();
                        this.terminalHistoryStep(-1);
                    } else if (event.key === 'ArrowDown') {
                        event.preventDefault();
                        this.terminalHistoryStep(1);
                    } else if (event.key === 'Escape') {
                        this.terminalOpen = false;
                    }
                },
                terminalHistoryStep(delta) {
                    if (this.terminalHistory.length === 0) {
                        return;
                    }

                    const next = this.terminalHistoryIndex + delta;
                    this.terminalHistoryIndex = Math.max(-1, Math.min(this.terminalHistory.length - 1, next));
                    this.terminalInput = this.terminalHistoryIndex === -1
                        ? ''
                        : this.terminalHistory[this.terminalHistoryIndex];
                },
                async runTerminal() {
                    const command = this.terminalInput.trim();

                    if (command === '') {
                        return;
                    }

                    this.terminalInput = '';
                    this.terminalHistory.push(command);
                    this.terminalHistoryIndex = -1;
                    this.terminalLines.push({ kind: 'in', text: '$ ' + command });

                    try {
                        const response = await fetch(this.routes.terminal, {
                            method: 'POST',
                            headers: { 'Accept': 'application/json', 'Content-Type': 'application/json' },
                            body: JSON.stringify({
                                command,
                                cwd: this.terminalCwd,
                                workspace: this.workspaceParam(),
                            }),
                        });

                        const payload = await response.json().catch(() => null);

                        if (!response.ok) {
                            this.terminalLines.push({ kind: 'err', text: payload?.message || 'The command was refused.' });
                        } else {
                            this.terminalCwd = payload.cwd ?? this.terminalCwd;

                            if (payload.output) {
                                payload.output.split('\n').forEach((text) => {
                                    this.terminalLines.push({ kind: payload.code ? 'err' : 'out', text });
                                });
                            }

                            if (payload.code) {
                                this.terminalLines.push({ kind: 'err', text: 'exit ' + payload.code });
                            }
                        }
                    } catch (error) {
                        this.terminalLines.push({ kind: 'err', text: error.message });
                    }

                    this.$nextTick(() => {
                        const output = this.$refs.terminalOut;

                        if (output) {
                            output.scrollTop = output.scrollHeight;
                        }

                        this.$refs.terminalInput?.focus();
                    });
                },
                async copyEditor(event) {
                    await navigator.clipboard?.writeText(this.contents);
                    const button = event.currentTarget;
                    const original = button.textContent;

                    button.textContent = 'Copied';
                    setTimeout(() => {
                        button.textContent = original;
                    }, 1200);
                },
            };
        }
    </script>
    @endpush
@endsection
