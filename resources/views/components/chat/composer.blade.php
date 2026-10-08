{{--
    The composer.

    It is a single rounded vessel holding, from top to bottom: any active
    suggestion panel (an enhanced prompt, @-mentions, slash commands), the
    selection toolbar, the textarea, and the control row. The suggestion panels
    float above the vessel so opening one never reflows the transcript.
--}}
<div class="shrink-0 bg-white px-4 pb-4 pt-3 dark:bg-[#212121] sm:px-6 lg:px-8">
    <div class="mx-auto max-w-3xl">

        

        {{-- Image mode turns the composer into a styled generation box. --}}
        <div x-show="mode === 'image'" class="mb-2" style="display:none">
            <x-chat.style-chips />
        </div>

        {{-- Composer mode chip (image generation) --}}
        <div x-show="mode === 'image'" class="mb-2 flex" style="display:none">
            <button
                type="button"
                x-on:click="mode = 'chat'"
                class="flex items-center gap-1.5 rounded-full bg-[#f4f4f4] px-3 py-1.5 text-xs
                       font-medium text-[#0d0d0d] transition hover:bg-black/[0.08]
                       focus-visible:outline-2 focus-visible:outline-accent dark:bg-[#303030]
                       dark:text-[#ececec] dark:hover:bg-white/[0.1]"
            >
                <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 0 0 1.5-1.5V6a1.5 1.5 0 0 0-1.5-1.5H3.75A1.5 1.5 0 0 0 2.25 6v12a1.5 1.5 0 0 0 1.5 1.5Z"/>
                </svg>
                Image mode
                <span aria-hidden="true">×</span>
            </button>
        </div>

        {{-- Attachment previews pending send --}}
        <div x-show="pendingFiles.length > 0" class="mb-2 flex flex-wrap gap-2" style="display:none">
            <template x-for="(file, fileIndex) in pendingFiles" :key="fileIndex">
                <div class="relative overflow-hidden rounded-xl border border-black/[0.08] bg-white
                            dark:border-white/[0.14] dark:bg-[#303030]">
                    <img x-show="file.kind === 'image'" alt="" :src="file.url" :alt="file.name"
                         class="h-16 w-16 object-cover">
                    <video x-show="file.kind === 'video'" :src="file.url"
                           class="h-16 w-24 bg-black object-contain" muted preload="metadata"></video>
                    <div class="flex items-center justify-between gap-2 px-2 py-1">
                        <span class="max-w-24 truncate text-[10px] text-[#8f8f8f]" x-text="file.name"></span>
                        <button
                            type="button"
                            x-on:click="removePendingFile(fileIndex)"
                            class="grid h-4 w-4 place-items-center rounded-full bg-black/10 text-[#5d5d5d]
                                   hover:bg-black/20 dark:bg-white/10 dark:text-[#b4b4b4] dark:hover:bg-white/20"
                            aria-label="Remove attachment"
                        >×</button>
                    </div>
                </div>
            </template>
        </div>

        {{-- File validation feedback --}}
        <p
            x-show="fileError"
            x-text="fileError"
            class="mb-2 rounded-lg bg-rose-50 px-2.5 py-1.5 text-[11px] text-rose-600
                   dark:bg-rose-500/10 dark:text-rose-400"
            style="display:none"
        ></p>

        {{-- Composer: one rounded vessel, controls on the row beneath the text --}}
        <div
            class="relative rounded-[26px] border border-black/[0.08] bg-white p-2
                   shadow-[0_2px_12px_rgba(0,0,0,0.06)] transition focus-within:border-black/20
                   dark:border-white/[0.14] dark:bg-[#303030] dark:focus-within:border-white/25"
        >
            {{-- Enhance preview: the rewrite sits above the draft until accepted or dismissed. --}}
            <div x-show="enhancedDraft" class="mb-1 rounded-xl bg-accent/[0.08] p-2.5" style="display:none">
                <div class="mb-1.5 flex items-center gap-1.5 text-[11px] font-medium text-accent">
                    <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 0 0-3.09 3.09Z"/>
                    </svg>
                    Enhanced prompt
                </div>
                <p class="whitespace-pre-wrap break-words px-0.5 text-[13px] leading-relaxed
                          text-[#0d0d0d] dark:text-[#ececec]" x-text="enhancedDraft"></p>
                <div class="mt-2 flex items-center gap-1.5">
                    <button type="button" x-on:click="acceptEnhanced()"
                            class="rounded-lg bg-accent px-2.5 py-1 text-[11px] font-medium text-white
                                   transition hover:brightness-110">
                        Use this
                    </button>
                    <button type="button" x-on:click="dismissEnhanced()"
                            class="rounded-lg px-2.5 py-1 text-[11px] font-medium text-[#5d5d5d] transition
                                   hover:bg-black/[0.06] dark:text-[#b4b4b4] dark:hover:bg-white/[0.1]">
                        Dismiss
                    </button>
                </div>
            </div>

            {{-- Suggestion panels float above the vessel so they never reflow the transcript. --}}
            <div class="relative">
                {{-- @-mention picker: workspace files and the sub-agents that can be named. --}}
                <div
                    x-show="mentionOpen && mentionMatches().length > 0"
                    x-transition:enter="transition ease-out duration-150"
                    x-transition:enter-start="opacity-0 translate-y-1"
                    x-transition:enter-end="opacity-100 translate-y-0"
                    class="absolute bottom-full left-0 z-30 mb-3 w-80 overflow-hidden rounded-xl
                           border border-black/[0.08] bg-white p-1 shadow-xl
                           dark:border-white/[0.14] dark:bg-[#303030]"
                    style="display:none"
                >
                    <p class="px-2.5 py-1.5 text-[11px] font-medium uppercase tracking-wide text-[#8f8f8f]">
                        Mention a file or an agent
                    </p>

                    <div class="whale-scroll max-h-72 overflow-y-auto">
                        <template x-for="(item, index) in mentionMatches()" :key="item.group + ':' + item.id">
                            <button
                                type="button"
                                x-on:click="applyMention(item)"
                                x-on:mouseenter="mentionIndex = index"
                                class="flex w-full flex-col gap-0.5 rounded-lg px-2.5 py-2 text-left transition"
                                :class="mentionIndex === index
                                    ? 'bg-black/[0.06] dark:bg-white/[0.1]'
                                    : 'hover:bg-black/[0.04] dark:hover:bg-white/[0.07]'"
                            >
                                <span class="flex items-center gap-2 text-sm font-medium">
                                    <span class="rounded-md bg-black/[0.06] px-1.5 py-0.5 text-[10px] uppercase
                                                 tracking-wide text-[#8f8f8f] dark:bg-white/10"
                                          x-text="item.group"></span>
                                    <span class="truncate" x-text="item.label"></span>
                                </span>
                                <span x-show="item.group === 'agent'"
                                      class="line-clamp-2 text-[11px] leading-snug text-[#8f8f8f]"
                                      x-text="item.description"
                                      style="display:none"></span>
                                <span x-show="item.group === 'file'"
                                      class="truncate font-mono text-[11px] text-[#8f8f8f]"
                                      x-text="item.path"
                                      style="display:none"></span>
                            </button>
                        </template>
                    </div>

                    <p class="border-t border-black/[0.06] px-2.5 py-1.5 text-[10px] text-[#8f8f8f]
                              dark:border-white/[0.1]">
                        ↑↓ to move · Enter to insert · Esc to dismiss
                    </p>
                </div>

                {{-- Slash commands: a leading "/" opens the command list. --}}
                <div
                    x-show="slashOpen && slashMatches().length > 0"
                    x-transition:enter="transition ease-out duration-150"
                    x-transition:enter-start="opacity-0 translate-y-1"
                    x-transition:enter-end="opacity-100 translate-y-0"
                    class="absolute bottom-full left-0 z-30 mb-3 w-80 overflow-hidden rounded-xl
                           border border-black/[0.08] bg-white p-1 shadow-xl
                           dark:border-white/[0.14] dark:bg-[#303030]"
                    style="display:none"
                >
                    <p class="px-2.5 py-1.5 text-[11px] font-medium uppercase tracking-wide text-[#8f8f8f]">
                        Commands
                    </p>

                    <div class="whale-scroll max-h-72 overflow-y-auto">
                        <template x-for="(command, index) in slashMatches()" :key="command.id">
                            <button
                                type="button"
                                x-on:click="runSlashCommand(command)"
                                x-on:mouseenter="slashIndex = index"
                                class="flex w-full items-start gap-2.5 rounded-lg px-2.5 py-2 text-left transition"
                                :class="slashIndex === index
                                    ? 'bg-black/[0.06] dark:bg-white/[0.1]'
                                    : 'hover:bg-black/[0.04] dark:hover:bg-white/[0.07]'"
                            >
                                <span class="grid h-6 w-6 shrink-0 place-items-center rounded-md
                                             bg-black/[0.06] text-[11px] font-semibold text-[#5d5d5d]
                                             dark:bg-white/10 dark:text-[#b4b4b4]"
                                      x-text="command.icon"></span>
                                <span class="min-w-0 flex-1">
                                    <span class="block truncate text-sm font-medium" x-text="command.label"></span>
                                    <span class="block text-[11px] leading-snug text-[#8f8f8f]"
                                          x-text="command.description"></span>
                                </span>
                            </button>
                        </template>
                    </div>
                </div>
            </div>

            {{-- Tool rail: the per-turn switches, always one tap away. It
                 scrolls sideways rather than wrapping so the composer keeps a
                 single predictable height on narrow screens. --}}
            <div class="relative mb-1.5 flex items-center gap-1 overflow-x-auto pb-0.5">
                <button
                    type="button"
                    x-on:click="web = !web"
                    :aria-pressed="web ? 'true' : 'false'"
                    class="shrink-0 rounded-full border px-2.5 py-1 text-[11px] font-medium transition
                           focus-visible:outline-2 focus-visible:outline-accent"
                    :class="web
                        ? 'border-accent bg-accent/10 text-accent'
                        : 'border-black/[0.08] text-[#5d5d5d] hover:bg-black/[0.05] dark:border-white/[0.14] dark:text-[#b4b4b4] dark:hover:bg-white/[0.08]'"
                    title="Allow the assistant to search the web"
                >
                    <span class="flex items-center gap-1">
                        <svg class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="9"/>
                            <path stroke-linecap="round" d="M3 12h18M12 3a15 15 0 0 1 0 18M12 3a15 15 0 0 0 0 18"/>
                        </svg>
                        Search
                    </span>
                </button>

                <button
                    type="button"
                    x-on:click="deepSearch = !deepSearch"
                    :aria-pressed="deepSearch ? 'true' : 'false'"
                    class="shrink-0 rounded-full border px-2.5 py-1 text-[11px] font-medium transition
                           focus-visible:outline-2 focus-visible:outline-accent"
                    :class="deepSearch
                        ? 'border-accent bg-accent/10 text-accent'
                        : 'border-black/[0.08] text-[#5d5d5d] hover:bg-black/[0.05] dark:border-white/[0.14] dark:text-[#b4b4b4] dark:hover:bg-white/[0.08]'"
                    title="Plan several queries and cross-check the sources"
                >
                    Deep search
                </button>

                {{-- Thinking effort: how much the model deliberates before answering. --}}
                <div class="relative shrink-0">
                    <button
                        type="button"
                        x-on:click="effortOpen = !effortOpen"
                        :aria-expanded="effortOpen ? 'true' : 'false'"
                        class="whale-chip"
                        title="How hard the model should think"
                    >
                        <x-ui.icon name="bolt" class="h-3 w-3" />
                        <span x-text="effortLabel()"></span>
                    </button>

                    <x-ui.panel show="effortOpen" side="top" width="w-60" label="Thinking effort">
                        <template x-for="option in efforts" :key="option.id">
                            <x-ui.menu-item
                                active="thinking === option.id"
                                x-on:click="setEffort(option.id); effortOpen = false"
                            >
                                <span class="block truncate" x-text="option.label"></span>
                                <span class="mt-0.5 block text-[11px] leading-snug text-muted" x-text="option.description"></span>
                            </x-ui.menu-item>
                        </template>
                    </x-ui.panel>
                </div>

                {{-- Response depth: how thorough the reply should be. --}}
                <div class="relative shrink-0">
                    <button
                        type="button"
                        x-on:click="depthOpen = !depthOpen"
                        :aria-expanded="depthOpen ? 'true' : 'false'"
                        class="whale-chip"
                        title="How thorough the answer should be"
                    >
                        <span x-text="depthLabel()"></span>
                    </button>

                    <x-ui.panel show="depthOpen" side="top" width="w-64" label="Response depth">
                        <template x-for="option in depths" :key="option.id">
                            <x-ui.menu-item
                                active="depth === option.id"
                                x-on:click="setDepth(option.id); depthOpen = false"
                            >
                                <span class="block truncate" x-text="option.label"></span>
                                <span class="mt-0.5 block text-[11px] leading-snug text-muted" x-text="option.description"></span>
                            </x-ui.menu-item>
                        </template>
                    </x-ui.panel>
                </div>

                {{-- Response length: how much the model is allowed to write.

                     A slider rather than a fourth dropdown, because length is
                     the one response-shaping setting where the middle ground is
                     genuinely useful and picking it by name means scrolling a
                     list. The tier is named underneath so the position is never
                     the only clue. --}}
                <div class="relative shrink-0">
                    <button
                        type="button"
                        x-on:click="lengthOpen = !lengthOpen"
                        :aria-expanded="lengthOpen ? 'true' : 'false'"
                        class="whale-chip"
                        title="How long the answer should be"
                    >
                        <x-ui.icon name="rows" class="h-3 w-3" />
                        <span x-text="lengthLabel()"></span>
                    </button>

                    <x-ui.panel show="lengthOpen" side="top" width="w-72" label="Response length">
                        <div class="px-2.5 py-2">
                            <div class="mb-2 flex items-baseline justify-between gap-3">
                                <span class="text-sm font-medium" x-text="lengthLabel()"></span>
                                <span class="text-[11px] text-muted" x-text="lengthIndex + 1 + ' of ' + lengths.length"></span>
                            </div>

                            <input
                                type="range"
                                class="whale-range"
                                min="0"
                                :max="lengths.length - 1"
                                step="1"
                                :value="lengthIndex"
                                x-on:input="setLengthIndex($event.target.value)"
                                x-on:change="lengthOpen = false"
                                aria-label="Response length"
                            >

                            <div class="mt-1.5 flex justify-between text-[10px] text-faint">
                                <template x-for="option in lengths" :key="option.id">
                                    <span x-text="option.label"></span>
                                </template>
                            </div>

                            <p class="mt-2 text-[11px] leading-snug text-muted" x-text="lengthDescription()"></p>
                        </div>
                    </x-ui.panel>
                </div>
            </div>

            {{-- Selection toolbar: appears while text is selected in the composer. --}}
            <div
                x-show="selectionLength > 0"
                x-transition:enter="transition ease-out duration-100"
                class="mb-1 flex items-center gap-0.5 rounded-lg bg-[#f4f4f4] px-1.5 py-1
                       dark:bg-white/[0.06]"
                style="display:none"
            >
                <button type="button" x-on:click="wrapSelection('**')" title="Bold"
                        class="grid h-6 w-6 place-items-center rounded-md text-xs font-bold text-[#5d5d5d]
                               transition hover:bg-black/[0.08] dark:text-[#b4b4b4] dark:hover:bg-white/[0.12]">B</button>
                <button type="button" x-on:click="wrapSelection('*')" title="Italic"
                        class="grid h-6 w-6 place-items-center rounded-md text-xs italic text-[#5d5d5d]
                               transition hover:bg-black/[0.08] dark:text-[#b4b4b4] dark:hover:bg-white/[0.12]">I</button>
                <button type="button" x-on:click="wrapSelection('`')" title="Inline code"
                        class="grid h-6 w-6 place-items-center rounded-md font-mono text-[11px] text-[#5d5d5d]
                               transition hover:bg-black/[0.08] dark:text-[#b4b4b4] dark:hover:bg-white/[0.12]">&lt;/&gt;</button>
                <button type="button" x-on:click="wrapSelection('```\n', '\n```')" title="Code block"
                        class="rounded-md px-1.5 py-0.5 text-[11px] text-[#5d5d5d] transition
                               hover:bg-black/[0.08] dark:text-[#b4b4b4] dark:hover:bg-white/[0.12]">block</button>
                <span class="mx-0.5 h-4 w-px bg-black/[0.1] dark:bg-white/[0.15]"></span>
                <button type="button" x-on:click="wrapSelection('- ')" title="Bullet list"
                        class="rounded-md px-1.5 py-0.5 text-[11px] text-[#5d5d5d] transition
                               hover:bg-black/[0.08] dark:text-[#b4b4b4] dark:hover:bg-white/[0.12]">• list</button>
                <button type="button" x-on:click="enhancePrompt()" :disabled="enhancing" title="Improve this prompt"
                        class="rounded-md px-1.5 py-0.5 text-[11px] font-medium text-accent transition
                               hover:bg-accent/[0.1] disabled:opacity-40">
                    <span x-text="enhancing ? 'Enhancing…' : 'Enhance'"></span>
                </button>
                <span class="ml-auto pr-1 text-[10px] tabular-nums text-[#8f8f8f]">
                    <span x-text="selectionLength"></span> selected
                </span>
            </div>

            <textarea
                x-ref="input"
                x-model="draft"
                x-on:input="autoGrow($event); syncSuggestions($event)"
                x-on:paste="handlePaste($event)"
                x-on:select="syncSelection($event)"
                x-on:keyup="syncSelection($event)"
                x-on:mouseup="syncSelection($event)"
                x-on:blur="onComposerBlur()"
                x-on:keydown.enter="onComposerEnter($event)"
                x-on:keydown.escape="dismissSuggestions()"
                x-on:keydown.down.prevent="moveSuggestion(1)"
                x-on:keydown.up.prevent="moveSuggestion(-1)"
                x-on:keydown.tab.prevent="acceptSuggestion()"
                rows="1"
                maxlength="20000"
                :placeholder="placeholder"

                class="min-h-[44px] max-h-[45vh] w-full resize-none border-0 bg-transparent px-3 py-2
                       text-[15px] leading-6 placeholder:text-[#8f8f8f]
                       focus:outline-none focus:ring-0 sm:max-h-[45vh]"
            ></textarea>

            <div class="flex items-center justify-between gap-2 px-1">
                <div class="flex items-center gap-0.5">
                    <div class="relative">
                        <button
                            type="button"
                            x-on:click="attachMenuOpen = !attachMenuOpen"
                            x-on:click.outside="attachMenuOpen = false"
                            :aria-expanded="attachMenuOpen ? 'true' : 'false'"
                            class="grid h-8 w-8 place-items-center rounded-full text-[#5d5d5d] transition
                                   hover:bg-black/[0.06] focus-visible:outline-2 focus-visible:outline-accent
                                   dark:text-[#b4b4b4] dark:hover:bg-white/[0.1]"
                            aria-label="Add content"
                        >
                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" d="M12 5v14M5 12h14"/>
                            </svg>
                        </button>

                        <div
                            x-show="attachMenuOpen"
                            x-transition:enter="transition ease-out duration-150"
                            x-transition:enter-start="opacity-0 translate-y-1"
                            x-transition:enter-end="opacity-100 translate-y-0"
                            class="absolute bottom-11 left-0 z-20 w-60 overflow-hidden rounded-xl
                                   border border-black/[0.08] bg-white p-1 shadow-xl
                                   dark:border-white/[0.14] dark:bg-[#303030]"
                            style="display:none"
                        >
                            <button
                                type="button"
                                x-on:click="attachMenuOpen = false; pickFiles()"
                                class="flex w-full items-center gap-2.5 rounded-lg px-3 py-2 text-left text-sm
                                       text-[#0d0d0d] transition hover:bg-black/[0.05]
                                       focus-visible:outline-2 focus-visible:outline-accent
                                       dark:text-[#ececec] dark:hover:bg-white/[0.08]"
                            >
                                <svg class="h-4 w-4 text-[#8f8f8f]" viewBox="0 0 24 24" fill="none"
                                     stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                          d="m18.375 12.739-7.693 7.693a4.5 4.5 0 0 1-6.364-6.364l10.94-10.94A3 3 0 1 1 19.5 7.372L8.552 18.32m.009-.01-.01.01m5.699-9.941-7.81 7.81a1.5 1.5 0 0 0 2.112 2.13"/>
                                </svg>
                                Upload image or video
                            </button>

                            <button
                                type="button"
                                x-on:click="attachMenuOpen = false; mode = 'image'; $nextTick(() => $refs.input.focus())"
                                class="flex w-full items-center gap-2.5 rounded-lg px-3 py-2 text-left text-sm
                                       text-[#0d0d0d] transition hover:bg-black/[0.05]
                                       focus-visible:outline-2 focus-visible:outline-accent
                                       dark:text-[#ececec] dark:hover:bg-white/[0.08]"
                            >
                                <svg class="h-4 w-4 text-[#8f8f8f]" viewBox="0 0 24 24" fill="none"
                                     stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                          d="M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 0 0-3.09 3.09Z"/>
                                </svg>
                                Generate an image
                            </button>

                            <button
                                type="button"
                                x-on:click="attachMenuOpen = false; startOcrPick()"
                                class="flex w-full items-center gap-2.5 rounded-lg px-3 py-2 text-left text-sm
                                       text-[#0d0d0d] transition hover:bg-black/[0.05]
                                       focus-visible:outline-2 focus-visible:outline-accent
                                       dark:text-[#ececec] dark:hover:bg-white/[0.08]"
                            >
                                <svg class="h-4 w-4 text-[#8f8f8f]" viewBox="0 0 24 24" fill="none"
                                     stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                          d="M3.75 4.875c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5A1.125 1.125 0 0 1 3.75 9.375v-4.5ZM3.75 14.625c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5a1.125 1.125 0 0 1-1.125-1.125v-4.5ZM13.5 4.875c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5a1.125 1.125 0 0 1-1.125-1.125v-4.5Z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                          d="M6.75 6.75h.75v.75h-.75v-.75ZM6.75 16.5h.75v.75h-.75v-.75ZM16.5 6.75h.75v.75h-.75v-.75ZM13.5 13.5h.75v.75h-.75v-.75ZM13.5 19.5h.75v.75h-.75v-.75ZM19.5 13.5h.75v.75h-.75v-.75ZM19.5 19.5h.75v.75h-.75v-.75ZM16.5 16.5h.75v.75h-.75v-.75Z"/>
                                </svg>
                                Scan image (OCR)
                            </button>

                            <button
                                type="button"
                                x-on:click="attachMenuOpen = false; openBoard()"
                                class="flex w-full items-center gap-2.5 rounded-lg px-3 py-2 text-left text-sm
                                       text-[#0d0d0d] transition hover:bg-black/[0.05]
                                       focus-visible:outline-2 focus-visible:outline-accent
                                       dark:text-[#ececec] dark:hover:bg-white/[0.08]"
                            >
                                <svg class="h-4 w-4 text-[#8f8f8f]" viewBox="0 0 24 24" fill="none"
                                     stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                          d="M16.862 4.487 18.549 2.8a2.499 2.499 0 0 1 3.535 3.535l-8.164 8.164a4.5 4.5 0 0 1-1.897 1.13L5 18.5l2.477-.025a4.5 4.5 0 0 1 1.13-1.897l8.164-8.164Z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 19.5h15"/>
                                </svg>
                                Draw or sketch
                            </button>
                        </div>
                    </div>

                    <input
                        x-ref="fileInput"
                        type="file"
                        multiple
                        class="hidden"
                        x-on:change="handleFiles($event.target.files); $event.target.value = ''"
                    >

                    {{-- Prompt enhancement: rewrites the draft, never sends it. --}}
                    <button
                        type="button"
                        x-on:click="enhancePrompt()"
                        :disabled="!draft.trim() || enhancing || isStreaming"
                        title="Improve this prompt"
                        class="flex h-8 items-center gap-1.5 rounded-full px-2.5 text-xs font-medium
                               text-[#5d5d5d] transition hover:bg-black/[0.06]
                               focus-visible:outline-2 focus-visible:outline-accent
                               disabled:cursor-not-allowed disabled:opacity-40
                               dark:text-[#b4b4b4] dark:hover:bg-white/[0.1]"
                    >
                        <svg class="h-4 w-4" :class="enhancing ? 'animate-spin' : ''" viewBox="0 0 24 24"
                             fill="none" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                  d="M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 0 0-3.09 3.09Z"/>
                        </svg>
                        <span class="hidden sm:inline" x-text="enhancing ? 'Enhancing…' : 'Enhance'"></span>
                    </button>
                </div>

                <div class="flex items-center gap-1">
                    <span class="hidden pr-1 text-[11px] tabular-nums text-[#8f8f8f] sm:inline"
                          x-show="draft.length > 16000" style="display:none">
                        <span x-text="draft.length"></span>/20000
                    </span>

                    {{-- The microphone sits with the other composer controls,
                         just before send: it fills this box, so it belongs with
                         the box rather than floating over the page. --}}
                    <x-chat.live-capture />

                    <button
                        type="button"
                        x-show="!isStreaming"
                        x-on:click="send()"
                        :disabled="(!draft.trim() && pendingFiles.length === 0) || isStreaming"
                        class="grid h-8 w-8 place-items-center rounded-full bg-[#0d0d0d] text-white
                               transition hover:bg-black/80 active:scale-95
                               focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent
                               disabled:cursor-not-allowed disabled:bg-black/15 disabled:text-black/30
                               dark:bg-white dark:text-[#0d0d0d] dark:hover:bg-[#ececec]
                               dark:disabled:bg-white/20 dark:disabled:text-[#0d0d0d]/40"
                        aria-label="Send message"
                    >
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                  d="M6 12 3.269 3.125A59.769 59.769 0 0 1 21.485 12 59.768 59.768 0 0 1 3.27 20.875L5.999 12Zm0 0h7.5"/>
                        </svg>
                    </button>

                    <button
                        type="button"
                        x-show="isStreaming"
                        x-on:click="stop()"
                        class="grid h-8 w-8 place-items-center rounded-full bg-[#0d0d0d] text-white
                               transition hover:bg-black/80 active:scale-95
                               focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent
                               dark:bg-white dark:text-[#0d0d0d] dark:hover:bg-[#ececec]"
                        style="display:none"
                        aria-label="Stop generating"
                    >
                        <svg class="h-3 w-3" viewBox="0 0 24 24" fill="currentColor">
                            <rect x="6" y="6" width="12" height="12" rx="1.5"/>
                        </svg>
                    </button>
                </div>
            </div>
        </div>

        <p role="status" aria-live="polite" class="sr-only" x-text="announcement"></p>

        <div class="mt-2 flex items-center justify-between gap-3 px-1 text-[11px] text-[#8f8f8f]">
            <span class="truncate">Whale AI can make mistakes. Verify important information.</span>
            <span class="shrink-0">
                <kbd class="rounded border border-black/[0.1] px-1 py-0.5 font-sans text-[10px]
                            dark:border-white/[0.15]">Shift</kbd>
                +
                <kbd class="rounded border border-black/[0.1] px-1 py-0.5 font-sans text-[10px]
                            dark:border-white/[0.15]">Enter</kbd>
                for a new line
            </span>
        </div>
    </div>
</div>
