{{-- The sender's own words sit in a tinted bubble on the right; replies get the
     brand avatar and a full-width column on the left, so the two sides read apart
     at a glance even before the bubbles are noticed. --}}
<div
    class="group flex w-full items-start gap-3"
    :class="msg.role === 'user' ? 'justify-end' : 'justify-start'"
>
    {{-- The same accent square the sidebar brands, so every reply is attributed
         without printing a name above each message. Decorative: the role is
         already in the markup, so it stays out of the accessibility tree. --}}
    <span
        x-show="msg.role !== 'user'"
        style="display:none"
        class="mt-0.5 grid h-7 w-7 shrink-0 place-items-center rounded-lg bg-accent text-white"
        aria-hidden="true"
    >
        <x-ui.icon name="sparkles" class="h-4 w-4" />
    </span>

    <div
        class="flex min-w-0 flex-col gap-1.5"
        :class="msg.role === 'user'
            ? 'max-w-[85%] items-end sm:max-w-[70%]'
            : 'min-w-0 flex-1 items-start'"
    >
        <div x-show="msg.reasoning && msg.reasoning.length > 0" class="w-full" style="display:none">
            <button
                type="button"
                x-on:click="msg.reasoningOpen = !msg.reasoningOpen"
                :aria-expanded="msg.reasoningOpen ? 'true' : 'false'"
                class="flex items-center gap-1.5 rounded-lg px-2 py-1 text-xs font-medium text-[#8f8f8f]
                       transition hover:bg-black/[0.05] focus-visible:outline-2 focus-visible:outline-accent
                       dark:hover:bg-white/[0.08]"
            >
                <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M12 18v-5.25m0 0-6-2.25a.75.75 0 0 1-.335-1.41L7.5 7.5l4.5 2.625a.75.75 0 0 1 .75 0l4.5-2.625 1.835 1.835a.75.75 0 0 1-.335 1.41L12 12.75Z"/>
                </svg>
                <span>Reasoning</span>
                <svg
                    class="h-3 w-3 transition-transform duration-200"
                    :class="msg.reasoningOpen ? 'rotate-180' : ''"
                    viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"
                >
                    <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5"/>
                </svg>
            </button>

            <div
                x-show="msg.reasoningOpen"
                x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="opacity-0 -translate-y-1"
                x-transition:enter-end="opacity-100 translate-y-0"
                class="mt-2 space-y-2 border-l-2 border-black/10 pl-3 dark:border-white/15"
                style="display:none"
            >
                <template x-for="block in msg.reasoning" :key="block.id">
                    <p
                        class="whitespace-pre-wrap break-words text-[0.87em] leading-relaxed text-[#8f8f8f]"
                        x-text="block.text"
                    ></p>
                </template>
            </div>
        </div>

        {{-- Attachment thumbnails (images / videos the user attached) --}}
        <div
            x-show="msg.attachments && msg.attachments.length > 0"
            class="flex flex-wrap justify-end gap-2"
            style="display:none"
        >
            <template x-for="(file, fileIndex) in (msg.attachments || [])" :key="fileIndex">
                <div class="relative overflow-hidden rounded-xl ring-1 ring-black/10 dark:ring-white/15">
                    <img
                        x-show="file.kind === 'image'"
                        alt=""
                        :src="file.url"
                        :alt="file.name"
                        class="max-h-40 w-32 object-cover"
                    >
                    <video
                        x-show="file.kind === 'video'"
                        :src="file.url"
                        class="max-h-40 w-40 bg-black object-contain"
                        controls
                        preload="metadata"
                    ></video>
                    <span
                        class="block max-w-32 truncate bg-black/40 px-2 py-0.5 text-[10px] text-white/90"
                        x-text="file.name"
                    ></span>
                </div>
            </template>
        </div>
{{-- Inline editor for a previous prompt (ChatGPT-style edit) --}}
        <div x-show="msg.editing" class="prompt-editor w-full" style="display:none">
            <div class="w-full rounded-2xl border border-black/10 bg-white p-2 shadow-sm
                        ring-4 ring-black/[0.04] dark:border-white/15 dark:bg-[#303030]
                        dark:ring-white/[0.06]">
                <textarea
                    x-ref="editInput"
                    x-model="editDraft"
                    x-on:input="autoGrow($event)"
                    x-on:keydown.escape.prevent="cancelEdit()"
                    x-on:keydown.enter="if (!$event.shiftKey && !$event.isComposing) { $event.preventDefault(); saveEdit(msg); }"
                    x-on:input="autoGrow($event)"
                    rows="2"
                    maxlength="20000"
                    class="max-h-[240px] w-full resize-none border-0 bg-transparent p-1.5 text-[15px]
                           leading-6 focus:outline-none"
                ></textarea>
                <div class="mt-1 flex items-center justify-end gap-2 px-1.5 pb-1">
                    <button
                        type="button"
                        x-on:click="cancelEdit()"
                        class="rounded-lg px-2.5 py-1 text-xs font-medium text-[#5d5d5d] transition
                               hover:bg-black/[0.05] focus-visible:outline-2 focus-visible:outline-accent
                               dark:text-[#b4b4b4] dark:hover:bg-white/[0.08]"
                    >
                        Cancel
                    </button>
                    <button
                        type="button"
                        x-on:click="saveEdit(msg)"
                        :disabled="!editDraft.trim()"
                        class="rounded-lg bg-[#0d0d0d] px-2.5 py-1 text-xs font-medium text-white
                               transition hover:bg-black/80 focus-visible:outline-2 focus-visible:outline-accent
                               disabled:cursor-not-allowed disabled:opacity-40
                               dark:bg-white dark:text-[#0d0d0d] dark:hover:bg-[#ececec]"
                    >
                        Save &amp; send
                    </button>
                </div>
            </div>
            <p class="mt-1 px-1 text-[11px] text-[#8f8f8f]">
                Editing truncates every reply after this message.
            </p>
        </div>

        {{-- Generated image card with shimmer loader --}}
        <div x-show="msg.kind === 'image'" class="w-full max-w-md" style="display:none">
            <div
                x-show="msg.imageState === 'loading'"
                class="animate-shimmer flex aspect-[3/2] items-center justify-center rounded-2xl
                       ring-1 ring-black/10 dark:ring-white/15"
            >
                <div class="flex flex-col items-center gap-2 text-[#5d5d5d] dark:text-[#b4b4b4]">
                    <svg class="h-6 w-6 animate-spin" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle class="opacity-25" cx="12" cy="12" r="9"/>
                        <path class="opacity-75" stroke-linecap="round" d="M21 12a9 9 0 0 0-9-9"/>
                    </svg>
                    <span class="text-xs font-medium">Painting your image…</span>
                </div>
            </div>

            <div
                x-show="msg.imageState === 'ready'"
                class="overflow-hidden rounded-2xl ring-1 ring-black/10 dark:ring-white/15"
                style="display:none"
            >
                <img
                    :src="msg.imageUrl"
                    alt="Generated image"
                    :alt="msg.imagePrompt || 'Generated image'"
                    x-on:load="msg.imageLoaded = true"
                    class="w-full transition-opacity duration-500"
                    :class="msg.imageLoaded ? 'opacity-100' : 'opacity-0'"
                >
                <div class="flex items-center justify-between gap-3 bg-[#f4f4f4] px-3 py-2 text-xs
                            dark:bg-[#303030]">
                    <span class="truncate text-[#8f8f8f]" :title="msg.imagePrompt" x-text="msg.imagePrompt"></span>
                    <a
                        :href="msg.imageUrl"
                        target="_blank"
                        rel="noopener"
                        class="font-medium text-accent hover:underline"
                    >Open full size</a>
                </div>
            </div>

            <div
                x-show="msg.imageState === 'error'"
                class="rounded-2xl bg-rose-50 p-3 text-sm text-rose-600 ring-1 ring-rose-200
                       dark:bg-rose-500/10 dark:text-rose-400 dark:ring-rose-400/20"
                style="display:none"
            >
                <p x-text="msg.imageError"></p>
                <button
                    type="button"
                    x-on:click="retryImage(msg)"
                    class="mt-2 rounded-lg bg-rose-600 px-2.5 py-1 text-xs font-medium text-white hover:bg-rose-700"
                >Try again</button>
            </div>
{{-- OCR result card --}}
        <div x-show="msg.kind === 'ocr'" class="w-full" style="display:none">
            <div class="rounded-2xl bg-[#f4f4f4] p-3.5 dark:bg-[#303030]">
                <div class="mb-2 flex items-center justify-between">
                    <span class="text-xs font-medium text-[#5d5d5d] dark:text-[#b4b4b4]">Extracted text</span>
                    <button
                        type="button"
                        x-on:click="copyText(msg.ocrText || '', $event)"
                        class="rounded-md px-2 py-0.5 text-xs font-medium text-[#5d5d5d] transition
                               hover:bg-black/[0.05] focus-visible:outline-2 focus-visible:outline-accent
                               dark:text-[#b4b4b4] dark:hover:bg-white/[0.08]"
                    >Copy</button>
                </div>

                <div x-show="msg.ocrState === 'loading'" class="flex items-center gap-2 text-xs text-[#8f8f8f]" style="display:none">
                    <svg class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle class="opacity-25" cx="12" cy="12" r="9"/>
                        <path class="opacity-75" stroke-linecap="round" d="M21 12a9 9 0 0 0-9-9"/>
                    </svg>
                    Reading the image…
                </div>

                <p
                    x-show="msg.ocrState === 'ready'"
                    x-text="msg.ocrText"
                    class="whitespace-pre-wrap break-words text-[0.93em] leading-relaxed
                           text-[#0d0d0d] dark:text-[#ececec]"
                    style="display:none"
                ></p>

                <div x-show="msg.ocrState === 'error'" style="display:none">
                    <p class="text-sm text-rose-600 dark:text-rose-400" x-text="msg.ocrError"></p>
                    <button
                        type="button"
                        x-on:click="retryOcr(msg)"
                        class="mt-2 rounded-lg bg-rose-600 px-2.5 py-1 text-xs font-medium text-white hover:bg-rose-700"
                    >Try again</button>
                </div>
            </div>
        </div>

        {{-- Speech (audio) player for this message --}}
        <div x-show="msg.audioUrl || msg.audioState === 'loading' || msg.audioState === 'error'" class="w-full" style="display:none">
            <div class="flex items-center gap-2 rounded-xl bg-[#f4f4f4] px-3 py-2 dark:bg-[#303030]">
                <svg
                    x-show="msg.audioState === 'loading'"
                    class="h-4 w-4 animate-spin text-[#8f8f8f]"
                    viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                    style="display:none"
                >
                    <circle class="opacity-25" cx="12" cy="12" r="9"/>
                    <path class="opacity-75" stroke-linecap="round" d="M21 12a9 9 0 0 0-9-9"/>
                </svg>
                <audio
                    x-show="msg.audioUrl"
                    :src="msg.audioUrl"
                    controls
                    class="h-9 w-full"
                    preload="none"
                    style="display:none"
                ></audio>
            </div>
            <p
                x-show="msg.audioState === 'error'"
                x-text="msg.audioError"
                class="px-1 text-[11px] text-rose-500"
                style="display:none"
            ></p>
        </div>
{{-- The user's own words sit in an accent-tinted bubble; assistant replies are
     plain full-width prose under the avatar. --}}
        <div
            x-show="!msg.editing && (!msg.kind || msg.kind === 'text')"
            class="whale-reading text-[#0d0d0d] dark:text-[#ececec]"
            :class="msg.role === 'user'
                ? 'max-w-full rounded-3xl bg-accent/10 px-4 py-2.5 ring-1 ring-accent/20 dark:bg-accent/15 dark:ring-accent/25'
                : 'w-full'"
            style="display:none"
        >
            <p x-show="msg.role === 'user'" class="whitespace-pre-wrap break-words">
                <span x-text="msg.content"></span>
                <span
                    x-show="msg.streaming"
                    class="ml-0.5 inline-block h-[0.95em] w-[2px] translate-y-[0.15em] animate-pulse rounded-full bg-current"
                    style="display:none"
                ></span>
            </p>

            <div
                x-show="msg.role !== 'user' && msg.content"
                class="prose-md"
                x-html="renderMarkdown(msg.content)"
            ></div>
            <span
                x-show="msg.role !== 'user' && msg.streaming"
                class="inline-block h-[0.95em] w-[2px] translate-y-[0.15em] animate-pulse rounded-full bg-current"
                style="display:none"
            ></span>

            <p
                x-show="msg.edited"
                class="mt-1 text-[10px] italic text-[#8f8f8f]"
                style="display:none"
            >Edited</p>

            <p
                x-show="msg.error"
                class="whitespace-pre-wrap break-words text-rose-600 dark:text-rose-400"
                :class="msg.content ? 'mt-2 border-t border-rose-200 pt-2 dark:border-rose-400/20' : ''"
                style="display:none"
                x-text="msg.error"
            ></p>

            <p
                x-show="msg.stopped && !msg.streaming"
                class="text-[11px] italic text-[#8f8f8f]"
                :class="msg.content || msg.error ? 'mt-1.5' : ''"
                style="display:none"
            >
                Stopped
            </p>
        </div>
{{-- Hover action rail: copy / edit / regenerate / listen. On touch widths there
     is no hover, so the rail stays visible there instead of being unreachable. --}}
        <div
            x-show="!msg.editing"
            class="flex items-center gap-0.5 transition-opacity duration-150
                   sm:opacity-0 sm:group-hover:opacity-100 sm:focus-within:opacity-100"
            style="display:none"
        >
            <button
                type="button"
                x-on:click="copyText(msg.content || '', $event)"
                title="Copy"
                class="grid h-8 w-8 place-items-center rounded-full text-[#5d5d5d] transition
                       hover:bg-black/[0.06] focus-visible:outline-2 focus-visible:outline-accent
                       dark:text-[#b4b4b4] dark:hover:bg-white/[0.1]"
            >
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="9" y="9" width="12" height="12" rx="2"/>
                    <path stroke-linecap="round" d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/>
                </svg>
            </button>

            <button
                x-show="msg.role === 'user' && !isStreaming && msg.content"
                type="button"
                x-on:click="startEdit(msg)"
                title="Edit prompt"
                class="grid h-8 w-8 place-items-center rounded-full text-[#5d5d5d] transition
                       hover:bg-black/[0.06] focus-visible:outline-2 focus-visible:outline-accent
                       dark:text-[#b4b4b4] dark:hover:bg-white/[0.1]"
                style="display:none"
            >
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M16.862 4.487l1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Z"/>
                </svg>
            </button>

            <button
                x-show="msg.role === 'assistant' && !isStreaming && isLastAssistant(msg) && (!msg.kind || msg.kind === 'text')"
                type="button"
                x-on:click="regenerate(msg)"
                title="Regenerate response"
                class="grid h-8 w-8 place-items-center rounded-full text-[#5d5d5d] transition
                       hover:bg-black/[0.06] focus-visible:outline-2 focus-visible:outline-accent
                       dark:text-[#b4b4b4] dark:hover:bg-white/[0.1]"
                style="display:none"
            >
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99"/>
                </svg>
            </button>

            <button
                x-show="msg.role === 'assistant' && msg.content && !msg.streaming && (!msg.kind || msg.kind === 'text')"
                type="button"
                x-on:click="speak(msg)"
                title="Read aloud"
                class="grid h-8 w-8 place-items-center rounded-full text-[#5d5d5d] transition
                       hover:bg-black/[0.06] focus-visible:outline-2 focus-visible:outline-accent
                       dark:text-[#b4b4b4] dark:hover:bg-white/[0.1]"
                style="display:none"
            >
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M19.114 5.636a9 9 0 0 1 0 12.728M16.463 8.288a5.25 5.25 0 0 1 0 7.424M6.75 8.25l4.72-4.72a.75.75 0 0 1 1.28.53v15.88a.75.75 0 0 1-1.28.53l-4.72-4.72H4.51c-.88 0-1.704-.507-1.938-1.354A9.009 9.009 0 0 1 2.25 12c0-.83.112-1.633.322-2.396C2.806 8.756 3.63 8.25 4.51 8.25H6.75Z"/>
                </svg>
            </button>

            {{-- Reactions: a lightweight thumbs up/down on any reply. --}}
            <template x-for="reaction in ['up', 'down']" :key="reaction">
                <button
                    type="button"
                    x-on:click="react(msg, reaction)"
                    :title="reaction === 'up' ? 'Good response' : 'Bad response'"
                    class="grid h-8 w-8 place-items-center rounded-full transition hover:bg-black/[0.06]
                           focus-visible:outline-2 focus-visible:outline-accent dark:hover:bg-white/[0.1]"
                    :class="msg.reaction === reaction ? 'text-accent' : 'text-[#5d5d5d] dark:text-[#b4b4b4]'"
                >
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                         :style="reaction === 'down' ? 'transform: rotate(180deg)' : ''">
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="M6.633 10.25c.806 0 1.533-.446 2.031-1.08a9.041 9.041 0 0 1 2.861-2.4c.723-.384 1.35-.956 1.653-1.715a4.498 4.498 0 0 0 .322-1.672V3a.75.75 0 0 1 .75-.75 2.25 2.25 0 0 1 2.25 2.25c0 1.152-.26 2.243-.723 3.218-.266.558.107 1.282.725 1.282h3.126c1.026 0 1.945.694 2.054 1.715.045.422.068.85.068 1.285a11.95 11.95 0 0 1-2.649 7.521c-.388.482-.987.729-1.605.729H13.48c-.483 0-.964-.078-1.423-.23l-3.114-1.04a4.501 4.501 0 0 0-1.423-.23H5.904M14.25 9h2.25M5.904 18.75c.083.205.173.405.27.602.197.4-.078.898-.523.898h-.908c-.889 0-1.713-.518-1.972-1.368a12 12 0 0 1-.521-3.507c0-1.553.295-3.036.831-4.398C3.387 9.953 4.167 9.5 5 9.5h1.053c.472 0 .745.556.5.96a8.958 8.958 0 0 0-1.302 4.665c0 1.194.232 2.333.654 3.375Z"/>
                    </svg>
                </button>
            </template>
        </div>

        <span
            class="px-1 text-[11px] text-[#8f8f8f] transition-opacity duration-200
                   sm:opacity-0 sm:group-hover:opacity-100"
            x-text="msg.time"
        ></span>
    </div>
</div>
        </div>