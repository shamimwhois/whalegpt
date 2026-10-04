{{-- Slide-over code sandbox: edit a snippet and preview it live in a sandboxed iframe. --}}
<div
    x-show="sandbox.open"
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
    aria-label="Code sandbox"
>
    <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" x-on:click="closeSandbox()"></div>

    <div
        class="relative ml-auto flex h-full w-full flex-col bg-white shadow-2xl sm:w-[min(56rem,100%)]
               dark:bg-[#212121]"
        x-on:click.outside="closeSandbox()"
    >
        <div class="flex h-14 shrink-0 items-center justify-between border-b border-black/[0.08] px-4
                    dark:border-white/[0.12]">
            <div class="flex min-w-0 items-center gap-2">
                <span class="grid h-6 w-6 place-items-center rounded-md bg-accent text-[10px] font-bold text-white">&lt;/&gt;</span>
                <span class="truncate text-sm font-semibold">Sandbox</span>
                <span
                    class="rounded-full bg-black/[0.06] px-2 py-0.5 text-[11px] font-medium text-[#5d5d5d]
                           dark:bg-white/10 dark:text-[#b4b4b4]"
                    x-text="sandbox.lang || 'html'"
                ></span>
                <span
                    x-show="sandbox.dirty"
                    class="h-1.5 w-1.5 rounded-full bg-amber-400"
                    title="Unsaved changes"
                    style="display:none"
                ></span>
            </div>

            <div class="flex items-center gap-1">
                <button
                    type="button"
                    x-on:click="runSandbox()"
                    class="rounded-lg bg-[#0d0d0d] px-2.5 py-1.5 text-xs font-medium text-white transition
                           hover:bg-black/80 focus-visible:outline-2 focus-visible:outline-accent
                           dark:bg-white dark:text-[#0d0d0d] dark:hover:bg-[#ececec]"
                >Run</button>
                <button
                    type="button"
                    x-on:click="copySandbox($event)"
                    class="rounded-lg px-2.5 py-1.5 text-xs font-medium text-[#5d5d5d] transition
                           hover:bg-black/[0.05] dark:text-[#b4b4b4] dark:hover:bg-white/[0.08]"
                >Copy</button>
                <button
                    type="button"
                    x-on:click="downloadSandbox()"
                    class="rounded-lg px-2.5 py-1.5 text-xs font-medium text-[#5d5d5d] transition
                           hover:bg-black/[0.05] dark:text-[#b4b4b4] dark:hover:bg-white/[0.08]"
                >Download</button>
                <button
                    type="button"
                    x-on:click="resetSandbox()"
                    :disabled="!sandbox.dirty"
                    class="rounded-lg px-2.5 py-1.5 text-xs font-medium text-[#5d5d5d] transition
                           hover:bg-black/[0.05] dark:text-[#b4b4b4] dark:hover:bg-white/[0.08]
                           disabled:cursor-not-allowed disabled:opacity-40"
                >Reset</button>
                <button
                    type="button"
                    x-on:click="closeSandbox()"
                    class="grid h-7 w-7 place-items-center rounded-lg text-[#5d5d5d] transition
                           hover:bg-black/[0.05] focus-visible:outline-2 focus-visible:outline-accent
                           dark:text-[#b4b4b4] dark:hover:bg-white/[0.08]"
                    aria-label="Close sandbox"
                >
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" d="M6 6l12 12M18 6 6 18"/>
                    </svg>
                </button>
            </div>
<div class="flex min-h-0 flex-1 flex-col md:flex-row">
            <div class="flex min-h-0 flex-1 flex-col border-b border-black/[0.08] md:border-b-0 md:border-r
                        dark:border-white/[0.12]">
                <div class="flex items-center justify-between border-b border-black/[0.08] px-3 py-2
                            dark:border-white/[0.12]">
                    <span class="text-xs font-medium text-[#5d5d5d] dark:text-[#b4b4b4]">Code</span>
                    <span class="text-[11px] text-[#8f8f8f]" x-text="`${sandbox.code.length} chars`"></span>
                </div>
                <textarea
                    x-ref="sandboxEditor"
                    x-model="sandbox.code"
                    x-on:input="onSandboxEdit()"
                    x-on:keydown.tab.prevent="insertSandboxTab($event)"
                    spellcheck="false"
                    class="min-h-64 flex-1 resize-none bg-white p-3 font-mono text-[13px] leading-relaxed
                           text-[#0d0d0d] focus:outline-none dark:bg-[#212121] dark:text-[#ececec]"
                ></textarea>
            </div>

            <div class="flex min-h-0 flex-1 flex-col">
                <div class="flex items-center justify-between border-b border-black/[0.08] px-3 py-2
                            dark:border-white/[0.12]">
                    <span class="text-xs font-medium text-[#5d5d5d] dark:text-[#b4b4b4]">Preview</span>
                    <span class="text-[11px] text-[#8f8f8f]" x-text="sandbox.previewNote"></span>
                </div>
                <iframe
                    x-ref="sandboxFrame"
                    sandbox="allow-scripts"
                    title="Sandbox preview"
                    class="min-h-64 flex-1 border-0 bg-white dark:bg-[#181818]"
                ></iframe>
            </div>
        </div>
    </div>
</div>
        </div>