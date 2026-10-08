{{--
    The draw and scratch board.

    A modal canvas for sketching an idea before asking the model to render it.
    The sketch is usable three ways — attached to the chat, saved into the
    workspace file tree, or used as the source image for a styled generation.

    All of the drawing logic lives in boardApp() on the page so the markup here
    stays declarative and the canvas element is only ever addressed by ref.
--}}
<div
    x-show="board.open"
    x-transition:enter="transition ease-out duration-200"
    x-transition:enter-start="opacity-0"
    x-transition:enter-end="opacity-100"
    x-transition:leave="transition ease-in duration-150"
    x-transition:leave-start="opacity-100"
    x-transition:leave-end="opacity-0"
    class="fixed inset-0 z-50 flex"
    style="display:none"
    role="dialog"
    aria-modal="true"
    aria-label="Draw and scratch board"
>
    <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" x-on:click="closeBoard()"></div>

    <div class="relative m-auto flex h-[92vh] w-[min(64rem,96vw)] flex-col overflow-hidden rounded-2xl
                border border-black/[0.08] bg-white shadow-2xl dark:border-white/[0.14] dark:bg-[#212121]"
         x-on:keydown.escape.window="closeBoard()">

        {{-- Header --}}
        <div class="flex h-12 shrink-0 items-center gap-3 border-b border-black/[0.08] px-4
                    dark:border-white/[0.12]">
            <p class="min-w-0 flex-1 truncate text-sm font-semibold">Draw &amp; scratch</p>

            <button
                type="button"
                x-on:click="boardUndo()"
                :disabled="board.history.length === 0"
                title="Undo"
                class="rounded-lg px-2.5 py-1 text-xs font-medium text-[#5d5d5d] transition hover:bg-black/[0.05]
                       disabled:cursor-not-allowed disabled:opacity-40
                       dark:text-[#b4b4b4] dark:hover:bg-white/[0.08]"
            >Undo</button>

            <button
                type="button"
                x-on:click="boardClear()"
                title="Clear the board"
                class="rounded-lg px-2.5 py-1 text-xs font-medium text-[#5d5d5d] transition hover:bg-black/[0.05]
                       dark:text-[#b4b4b4] dark:hover:bg-white/[0.08]"
            >Clear</button>

            <button
                type="button"
                x-on:click="closeBoard()"
                class="grid h-8 w-8 place-items-center rounded-lg text-[#5d5d5d] transition hover:bg-black/[0.05]
                       focus-visible:outline-2 focus-visible:outline-accent
                       dark:text-[#b4b4b4] dark:hover:bg-white/[0.08]"
                aria-label="Close board"
            >
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" d="M6 6l12 12M18 6 6 18"/>
                </svg>
            </button>
        </div>

        {{-- Tools --}}
        <div class="flex shrink-0 flex-wrap items-center gap-2 border-b border-black/[0.08] px-4 py-2
                    dark:border-white/[0.12]">
            <div class="flex items-center gap-1 rounded-lg bg-[#f4f4f4] p-1 dark:bg-white/[0.06]">
                {{-- Freehand tools --}}
                <template x-for="option in [
                    { id: 'brush', label: 'Brush' },
                    { id: 'eraser', label: 'Eraser' },
                ]" :key="option.id">
                    <button
                        type="button"
                        x-on:click="board.tool = option.id"
                        :aria-pressed="board.tool === option.id ? 'true' : 'false'"
                        class="rounded-md px-2.5 py-1 text-[11px] font-medium transition focus-visible:outline-2
                               focus-visible:outline-accent"
                        :class="board.tool === option.id
                            ? 'bg-white shadow-sm dark:bg-[#303030]'
                            : 'text-[#8f8f8f] hover:text-[#5d5d5d]'"
                        x-text="option.label"
                    ></button>
                </template>

                <span class="mx-0.5 h-4 w-px bg-black/10 dark:bg-white/10"></span>

                {{-- Shapes are dragged rather than painted, so they read as a
                     separate group in the toolbar. --}}
                <template x-for="option in [
                    { id: 'line', label: 'Line' },
                    { id: 'rect', label: 'Rect' },
                    { id: 'ellipse', label: 'Ellipse' },
                ]" :key="option.id">
                    <button
                        type="button"
                        x-on:click="board.tool = option.id"
                        :aria-pressed="board.tool === option.id ? 'true' : 'false'"
                        class="rounded-md px-2.5 py-1 text-[11px] font-medium transition focus-visible:outline-2
                               focus-visible:outline-accent"
                        :class="board.tool === option.id
                            ? 'bg-white shadow-sm dark:bg-[#303030]'
                            : 'text-[#8f8f8f] hover:text-[#5d5d5d]'"
                        x-text="option.label"
                    ></button>
                </template>
            </div>

            {{-- Palette --}}
            <div class="flex items-center gap-1">
                <template x-for="colour in boardPalette()" :key="colour">
                    <button
                        type="button"
                        x-on:click="board.colour = colour; board.tool = 'brush'"
                        :aria-label="`Use ${colour}`"
                        class="h-6 w-6 rounded-full border transition"
                        :style="`background:${colour}`"
                        :class="board.colour === colour && board.tool === 'brush'
                            ? 'border-accent ring-2 ring-accent/40'
                            : 'border-black/10 dark:border-white/20'"
                    ></button>
                </template>

                <label class="ml-1 grid h-6 w-6 cursor-pointer place-items-center rounded-full border
                              border-dashed border-black/20 text-[10px] text-[#8f8f8f]
                              dark:border-white/25"
                       title="Custom colour">
                    <input type="color" x-model="board.colour" class="h-0 w-0 opacity-0">＋
                </label>
            </div>

            {{-- Brush size --}}
            <label class="flex items-center gap-2 text-[11px] text-[#8f8f8f]">
                Size
                <input
                    type="range"
                    min="1"
                    max="48"
                    x-model.number="board.size"
                    class="w-24 accent-[#10a37f]"
                >
                <span class="w-6 tabular-nums" x-text="board.size"></span>
            </label>
        </div>

        {{-- Canvas + side panel. Below the lg breakpoint the panel stacks under
             the canvas, so both get an explicit share of the height — without
             it the panel's content squeezes the canvas to zero and the board
             renders as an empty box. --}}
        <div class="flex min-h-0 flex-1 flex-col lg:flex-row">
            <div class="flex min-h-[200px] flex-1 items-center justify-center overflow-hidden bg-[#f4f4f4] p-3
                        dark:bg-[#1a1a1a]">
                {{-- The overlay sits exactly on top of the canvas and carries the
                     in-progress shape. Keeping it separate means a cancelled drag
                     never marks the drawing, and the committed canvas only ever
                     holds finished work. --}}
                <div class="relative max-h-full max-w-full">
                    <canvas
                        x-ref="boardCanvas"
                        width="1200"
                        height="760"
                        x-on:pointerdown="boardPointerDown($event)"
                        x-on:pointermove="boardPointerMove($event)"
                        x-on:pointerup="boardPointerUp($event)"
                        x-on:pointercancel="boardPointerUp($event)"
                        x-on:pointerleave="boardPointerUp($event)"
                        class="block max-h-full max-w-full touch-none rounded-xl bg-white shadow-sm ring-1 ring-black/10
                               dark:ring-white/15"
                        style="aspect-ratio: 1200 / 760"
                    ></canvas>

                    <canvas
                        x-ref="boardOverlay"
                        width="1200"
                        height="760"
                        aria-hidden="true"
                        class="pointer-events-none absolute inset-0 h-full w-full touch-none"
                    ></canvas>
                </div>
            </div>

            <aside class="flex h-[34vh] shrink-0 flex-col gap-3 overflow-y-auto border-t border-black/[0.08]
                          p-4 lg:h-auto lg:w-72 lg:border-l lg:border-t-0 dark:border-white/[0.12]">
                <div class="flex items-center justify-between text-[11px] text-[#8f8f8f]">
                    <span id="boardHint" class="hidden sm:inline">Draw, then use it as a reference.</span>
                    <span x-show="board.busy" style="display:none">Working…</span>
                </div>

                {{-- Sketch to styled image --}}
                <div>
                    <label for="board-prompt" class="text-[11px] font-medium uppercase tracking-wide text-[#8f8f8f]">
                        Turn this into an image
                    </label>
                    <textarea
                        id="board-prompt"
                        x-ref="boardPrompt"
                        x-model="board.prompt"
                        rows="2"
                        maxlength="2000"
                        placeholder="A whale cresting a wave…"
                        class="mt-1.5 w-full resize-none rounded-xl border border-black/[0.1] bg-white p-2.5
                               text-sm leading-5 placeholder:text-[#8f8f8f]
                               focus:border-accent focus:outline-none focus:ring-2 focus:ring-accent/20
                               dark:border-white/[0.14] dark:bg-[#303030]"
                    ></textarea>

                    <div class="mt-2">
                        <x-chat.style-chips />
                    </div>

                    <button
                        type="button"
                        x-on:click="generateFromBoard()"
                        :disabled="board.busy || !board.prompt.trim()"
                        class="mt-2 w-full rounded-xl bg-[#0d0d0d] px-3 py-2 text-sm font-medium text-white
                               transition hover:bg-black/80 focus-visible:outline-2 focus-visible:outline-accent
                               disabled:cursor-not-allowed disabled:opacity-40
                               dark:bg-white dark:text-[#0d0d0d] dark:hover:bg-[#ececec]"
                        x-text="board.busy ? 'Generating…' : 'Generate from sketch'"
                    ></button>

                    <div x-show="board.resultUrl" class="mt-3" style="display:none">
                        <img :src="board.resultUrl" alt="Generated from your sketch"
                             class="w-full rounded-xl ring-1 ring-black/10 dark:ring-white/15">
                        <a :href="board.resultUrl" target="_blank" rel="noopener"
                           class="mt-1 inline-block text-[11px] font-medium text-accent hover:underline">
                            Open full size
                        </a>
                    </div>

                    <p x-show="board.error" x-text="board.error"
                       class="mt-2 rounded-lg bg-rose-50 px-2.5 py-1.5 text-[11px] text-rose-600
                              dark:bg-rose-500/10 dark:text-rose-400"
                       style="display:none"></p>
                </div>

                <div class="grid grid-cols-1 gap-1.5 border-t border-black/[0.08] pt-3 dark:border-white/[0.12]">
                    <button
                        type="button"
                        x-on:click="attachBoard()"
                        class="rounded-xl border border-black/[0.1] px-3 py-2 text-sm font-medium text-[#5d5d5d]
                               transition hover:bg-black/[0.05]
                               dark:border-white/[0.14] dark:text-[#b4b4b4] dark:hover:bg-white/[0.08]"
                    >Attach to chat</button>

                    <button
                        type="button"
                        x-on:click="saveBoardToWorkspace()"
                        class="rounded-xl border border-black/[0.1] px-3 py-2 text-sm font-medium text-[#5d5d5d]
                               transition hover:bg-black/[0.05]
                               dark:border-white/[0.14] dark:text-[#b4b4b4] dark:hover:bg-white/[0.08]"
                    >Save to workspace</button>

                    <button
                        type="button"
                        x-on:click="downloadBoard()"
                        class="rounded-xl border border-black/[0.1] px-3 py-2 text-sm font-medium text-[#5d5d5d]
                               transition hover:bg-black/[0.05]
                               dark:border-white/[0.14] dark:text-[#b4b4b4] dark:hover:bg-white/[0.08]"
                    >Download PNG</button>
                </div>
            </aside>
        </div>
    </div>
</div>
