{{-- Provider settings: what this installation can run, and what to configure to widen it. --}}
<div
    x-show="settingsOpen"
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
    aria-label="Provider settings"
>
    <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" x-on:click="settingsOpen = false"></div>

    <div
        class="relative ml-auto flex h-full w-full flex-col bg-white shadow-2xl sm:w-[min(38rem,100%)]
               dark:bg-[#212121]"
        x-on:click.outside="settingsOpen = false"
    >
        <div class="flex h-14 shrink-0 items-center justify-between border-b border-black/[0.08] px-4
                    dark:border-white/[0.12]">
            <div class="min-w-0">
                <p class="truncate text-sm font-semibold">Model providers</p>
                <p class="truncate text-xs text-[#8f8f8f]">
                    <span x-text="configuredProviderCount()"></span> of
                    <span x-text="providers.length"></span> configured
                </p>
            </div>

            <button
                type="button"
                x-on:click="settingsOpen = false"
                class="grid h-8 w-8 place-items-center rounded-lg text-[#5d5d5d] transition
                       hover:bg-black/[0.05] focus-visible:outline-2 focus-visible:outline-accent
                       dark:text-[#b4b4b4] dark:hover:bg-white/[0.08]"
                aria-label="Close settings"
            >
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" d="M6 6l12 12M18 6 6 18"/>
                </svg>
            </button>
        </div>

        <div class="whale-scroll min-h-0 flex-1 overflow-y-auto p-4">
            <p class="mb-4 rounded-xl bg-[#f4f4f4] px-3.5 py-3 text-[13px] leading-relaxed
                      text-[#5d5d5d] dark:bg-[#303030] dark:text-[#b4b4b4]">
                Credentials are read from your <span class="font-mono">.env</span> file and never sent to
                the browser. Add the variable shown for a provider, then reload this page.
            </p>

            <div class="mt-6">
                <div class="mb-2 flex items-baseline justify-between gap-2">
                    <h2 class="text-sm font-semibold">Local models</h2>
                    <span class="truncate font-mono text-[11px] text-[#8f8f8f]" x-text="modelsPath"></span>
                </div>

                <p class="mb-3 rounded-xl bg-[#f4f4f4] px-3.5 py-3 text-[13px] leading-relaxed
                          text-[#5d5d5d] dark:bg-[#303030] dark:text-[#b4b4b4]">
                    Drop a <span class="font-mono">.gguf</span> file into that directory and it is detected
                    automatically. Capabilities are inferred from the file's architecture and tensor names;
                    the weights are never loaded by the app — a local runtime serves them.
                </p>

                <div x-show="localModels.length === 0" class="rounded-2xl border border-dashed
                            border-black/[0.12] px-4 py-6 text-center text-sm text-[#8f8f8f]
                            dark:border-white/[0.15]">
                    No model files found yet.
                </div>

                <div class="flex flex-col gap-2">
                    <template x-for="model in localModels" :key="model.id">
                        <div class="rounded-2xl border border-black/[0.08] px-4 py-3
                                    dark:border-white/[0.12]"
                             :class="model.servable ? '' : 'opacity-70'">
                            <div class="flex items-center gap-2">
                                <span class="h-2 w-2 shrink-0 rounded-full"
                                      :class="model.servable ? 'bg-accent' : 'bg-[#8f8f8f]'"></span>
                                <p class="min-w-0 flex-1 truncate text-sm font-semibold" x-text="model.name"></p>
                                <span class="shrink-0 text-[11px] text-[#8f8f8f]"
                                      x-text="formatBytes(model.size_bytes)"></span>
                            </div>

                            <p class="mt-1 truncate font-mono text-[11px] text-[#8f8f8f]"
                               x-text="model.file"></p>

                            <div class="mt-2 flex flex-wrap gap-1.5">
                                <template x-for="capability in model.capabilities" :key="capability">
                                    <span class="rounded-md bg-black/[0.06] px-2 py-0.5 text-[11px]
                                                 text-[#5d5d5d] dark:bg-white/10 dark:text-[#b4b4b4]"
                                          x-text="capability"></span>
                                </template>
                                <span class="rounded-md bg-black/[0.06] px-2 py-0.5 text-[11px] text-[#8f8f8f]
                                             dark:bg-white/10"
                                      x-text="model.runtime.name"></span>
                            </div>

                            <p class="mt-2 text-[11px] text-[#8f8f8f]"
                               x-show="model.context_length"
                            >
                                <span x-text="model.context_length"></span> ctx
                                <span x-show="model.layers"> · <span x-text="model.layers"></span> layers</span>
                                <span x-show="model.tensor_count">
                                    · <span x-text="model.tensor_count"></span> tensors
                                </span>
                            </p>

                            <p class="mt-2 text-[11px] text-[#8f8f8f]" x-show="!model.servable">
                                <span x-text="model.runtime.command
                                    ? 'Run: ' + model.runtime.command
                                    : 'No local runtime configured for this capability.'"></span>
                            </p>
                        </div>
                    </template>
                </div>
            </div>

            <div class="flex flex-col gap-2">
                <template x-for="provider in providers" :key="provider.name">
                    <div
                        class="rounded-2xl border border-black/[0.08] px-4 py-3 dark:border-white/[0.12]"
                        :class="provider.configured ? '' : 'opacity-70'"
                    >
                        <div class="flex items-center gap-2">
                            <span
                                class="h-2 w-2 shrink-0 rounded-full"
                                :class="provider.configured ? 'bg-accent' : 'bg-[#8f8f8f]'"
                            ></span>
                            <p class="min-w-0 flex-1 truncate text-sm font-semibold" x-text="provider.label"></p>
                            <span
                                x-show="provider.default"
                                class="shrink-0 rounded-full bg-black/[0.06] px-2 py-0.5 text-[10px]
                                       font-medium text-[#5d5d5d] dark:bg-white/10 dark:text-[#b4b4b4]"
                            >default</span>
                        </div>

                        <div class="mt-2 flex flex-wrap gap-1.5">
                            <template x-for="capability in provider.capabilities" :key="capability">
                                <span
                                    class="rounded-md bg-black/[0.06] px-2 py-0.5 text-[11px] text-[#5d5d5d]
                                           dark:bg-white/10 dark:text-[#b4b4b4]"
                                    x-text="capability"
                                ></span>
                            </template>

                            <span
                                x-show="provider.capabilities.length === 0"
                                class="rounded-md bg-black/[0.06] px-2 py-0.5 text-[11px] text-[#8f8f8f]
                                       dark:bg-white/10"
                            >no models configured</span>
                        </div>

                        <p
                            x-show="provider.configured && provider.models.length > 0"
                            class="mt-2 truncate font-mono text-[11px] text-[#8f8f8f]"
                            x-text="provider.models.filter((model) => model.configured).map((model) => model.id).join(', ')"
                        ></p>

                        <p
                            x-show="!provider.configured"
                            class="mt-2 text-[11px] text-[#8f8f8f]"
                        >
                            Set <span class="font-mono" x-text="provider.environment"></span> in your .env file
                        </p>
                    </div>
                </template>
            </div>
        </div>
    </div>
</div>