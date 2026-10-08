{{-- Provider settings: what this installation can run, and what to configure to widen it. --}}
<div
    x-show="settingsOpen"
    x-init="$watch('settingsOpen', (open) => {
            if (! open) {
                releaseFocus();

                return;
            }

            trapFocusIn($el);

            // Settings can be opened from the palette, the header or the model
            // picker, so the data it shows is fetched here rather than at each
            // call site. Without this the section opens empty.
            loadCustomProviders();
            loadLocalModels();
        })"
    x-on:keydown.escape="settingsOpen = false"
    x-on:keydown.tab="keepFocusInside($event)"
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
    aria-label="Settings"
>
    <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" x-on:click="settingsOpen = false"></div>

    <div
        class="relative ml-auto flex h-full w-full flex-col bg-page shadow-2xl sm:w-[min(38rem,100%)]"
        x-on:click.outside="settingsOpen = false"
    >
        <div class="flex h-14 shrink-0 items-center justify-between border-b border-line px-4">
            <div class="min-w-0">
                <p class="truncate text-sm font-semibold">Settings</p>
                <p class="truncate text-xs text-muted">
                    <span x-text="configuredProviderCount()"></span> of
                    <span x-text="providers.length"></span> providers configured
                </p>
            </div>

            <button
                type="button"
                x-on:click="settingsOpen = false"
                class="whale-icon-button"
                aria-label="Close settings"
            >
                <x-ui.icon name="close" />
            </button>
        </div>

        <div class="whale-scroll min-h-0 flex-1 overflow-y-auto p-4">
            {{-- Reading preferences. These are pure display choices: they write
                 CSS custom properties and one class, and are never sent to the
                 server. They sit above the provider list because they affect the
                 page you are reading while the rest only affects what the model
                 can do. --}}
            <section aria-labelledby="whale-appearance-heading">
                <div class="mb-2 flex items-baseline justify-between gap-2">
                    <h2 id="whale-appearance-heading" class="text-sm font-semibold">Appearance</h2>

                    <button
                        type="button"
                        x-on:click="resetPreferences()"
                        class="shrink-0 rounded-md px-2 py-1 text-[11px] font-medium text-muted
                               transition hover:bg-wash hover:text-ink
                               focus-visible:outline-2 focus-visible:outline-accent"
                    >
                        Reset
                    </button>
                </div>

                <div class="flex flex-col gap-3 rounded-2xl border border-line px-4 py-3">
                    <div>
                        <div class="flex items-baseline justify-between gap-2">
                            <label for="whale-reading-scale" class="text-[13px] font-medium">
                                Text size
                            </label>
                            <span class="text-[11px] tabular-nums text-muted"
                                  x-text="Math.round(readingScale * 100) + '%'"
                            ></span>
                        </div>

                        <input
                            id="whale-reading-scale"
                            type="range"
                            class="whale-range mt-2"
                            min="0.9"
                            max="1.25"
                            step="0.05"
                            x-model.number="readingScale"
                            x-on:input="setReadingScale(readingScale)"
                        >
                    </div>

                    <div>
                        <div class="flex items-baseline justify-between gap-2">
                            <label for="whale-transcript-density" class="text-[13px] font-medium">
                                Transcript spacing
                            </label>
                            <span class="text-[11px] tabular-nums text-muted"
                                  x-text="transcriptDensity.toFixed(1) + ' rem'"
                            ></span>
                        </div>

                        <input
                            id="whale-transcript-density"
                            type="range"
                            class="whale-range mt-2"
                            min="0.5"
                            max="3"
                            step="0.25"
                            x-model.number="transcriptDensity"
                            x-on:input="setTranscriptDensity(transcriptDensity)"
                        >
                    </div>

                    <div class="flex items-center gap-3">
                        <input
                            id="whale-reduce-motion"
                            type="checkbox"
                            class="h-4 w-4 shrink-0 accent-[#10a37f] focus-visible:outline-2
                                   focus-visible:-outline-offset-2 focus-visible:outline-accent"
                            x-model="reduceMotion"
                            x-on:change="setReduceMotion(reduceMotion)"
                        >
                        <label for="whale-reduce-motion" class="min-w-0 flex-1">
                            <span class="block text-[13px] font-medium">Reduce motion</span>
                            <span class="mt-0.5 block text-[11px] leading-snug text-muted">
                                Turns off transitions and animated spinners. Your system's
                                reduce-motion setting is always honoured, regardless of this.
                            </span>
                        </label>
                    </div>
                </div>
            </section>

            <p class="my-4 rounded-xl bg-sunken px-3.5 py-3 text-[13px] leading-relaxed text-body">
                Credentials are read from your <span class="font-mono">.env</span> file and never sent to
                the browser. Add the variable shown for a provider, then reload this page.
            </p>

            <div class="mt-6">
                <div class="mb-2 flex items-baseline justify-between gap-2">
                    <h2 class="text-sm font-semibold">Local models</h2>
                    <span class="truncate font-mono text-[11px] text-muted" x-text="modelsPath"></span>
                </div>

                <p class="mb-3 rounded-xl bg-sunken px-3.5 py-3 text-[13px] leading-relaxed text-body">
                    Drop a <span class="font-mono">.gguf</span> or
                    <span class="font-mono">.safetensors</span> file into that
                    directory and it is detected automatically. Capabilities are
                    inferred from the file's architecture and tensor names; the
                    weights are never loaded by the app — a local runtime serves them.
                </p>

                {{-- Which runtimes are actually answering, probed when the panel opened. --}}
                <div class="mb-3 flex flex-col gap-1.5">
                    <template x-for="(status, name) in runtimeHealth" :key="name">
                        <div class="flex items-start gap-2 text-[11px]">
                            <span
                                class="mt-1 h-1.5 w-1.5 shrink-0 rounded-full"
                                :class="{
                                    online: 'bg-emerald-500',
                                    unconfigured: 'bg-faint',
                                    unreachable: 'bg-amber-500',
                                    unknown: 'bg-faint',
                                }[status.status] ?? 'bg-faint'"
                            ></span>
                            <span class="font-medium text-body" x-text="name"></span>
                            <span class="text-muted" x-text="status.status"></span>
                            <span class="min-w-0 flex-1 truncate text-muted" x-text="status.detail ?? ''"></span>
                        </div>
                    </template>
                </div>

                <div
                    x-show="localModels.length === 0"
                    class="rounded-2xl border border-dashed border-line-strong px-4 py-6 text-center
                           text-sm text-muted"
                >
                    No model files found yet.
                </div>

                <div class="flex flex-col gap-2">
                    <template x-for="model in localModels" :key="model.id">
                        <div
                            class="rounded-2xl border border-line px-4 py-3"
                            :class="model.configured ? '' : 'opacity-70'"
                        >
                            <div class="flex items-center gap-2">
                                <span
                                    class="h-2 w-2 shrink-0 rounded-full"
                                    :class="model.configured ? 'bg-accent' : 'bg-faint'"
                                ></span>
                                <p class="min-w-0 flex-1 truncate text-sm font-semibold" x-text="model.name"></p>
                                <span class="shrink-0 text-[11px] text-muted"
                                      x-text="formatBytes(model.size_bytes)"></span>
                            </div>

                            <p class="mt-1 truncate font-mono text-[11px] text-muted" x-text="model.file"></p>

                            <div class="mt-2 flex flex-wrap gap-1.5">
                                <template x-for="capability in model.capabilities" :key="capability">
                                    <span
                                        class="whale-chip px-2 py-0.5 text-[11px]"
                                        x-text="capability"
                                    ></span>
                                </template>
                                <span class="whale-chip px-2 py-0.5 text-[11px] text-muted"
                                      x-text="model.runtime.name"></span>
                                <span class="whale-chip px-2 py-0.5 text-[11px] text-muted"
                                      x-show="model.format"
                                      x-text="model.format.toUpperCase()"></span>
                                <span class="whale-chip px-2 py-0.5 text-[11px] text-muted"
                                      x-show="model.quantization"
                                      x-text="model.quantization"></span>
                            </div>

                            <p class="mt-2 text-[11px] text-muted" x-show="model.context_length">
                                <span x-text="model.context_length"></span> ctx
                                <span x-show="model.layers"> · <span x-text="model.layers"></span> layers</span>
                                <span x-show="model.tensor_count">
                                    · <span x-text="model.tensor_count"></span> tensors
                                </span>
                            </p>

                            <p class="mt-2 text-[11px] text-muted" x-show="!model.configured">
                                <span x-text="model.runtime.command
                                    ? 'Run: ' + model.runtime.command
                                    : 'No local runtime configured for this capability.'"></span>
                            </p>
                        </div>
                    </template>
                </div>

                {{-- A file that carries a model extension but whose header could not
                     be read. Shown rather than hidden: silently dropping it makes a
                     half-finished download look like the app failed to detect. --}}
                <div class="mt-3 flex flex-col gap-1.5" x-show="unreadableModels.length > 0">
                    <p class="text-[11px] font-medium text-amber-600 dark:text-amber-400">
                        Files that could not be read
                    </p>

                    <template x-for="entry in unreadableModels" :key="entry.file">
                        <p class="truncate text-[11px] text-muted">
                            <span class="font-mono" x-text="entry.file"></span>
                            — <span x-text="entry.reason?.message ?? 'unreadable'"></span>
                        </p>
                    </template>
                </div>
            </div>

            <h2 class="mb-2 mt-6 text-sm font-semibold">Custom providers</h2>

            <p class="mb-3 rounded-xl bg-sunken px-3.5 py-3 text-[13px] leading-relaxed text-body">
                Any OpenAI-compatible endpoint can be added by listing it in
                <span class="font-mono">WHALE_CUSTOM_PROVIDERS</span> as a JSON array of
                <span class="font-mono">name</span>, <span class="font-mono">url</span> and
                <span class="font-mono">key</span>. Load what an endpoint serves, keep the models
                you want, and sync later to pick up anything that changed.
            </p>

            <div
                x-show="customProviders.length === 0"
                class="rounded-2xl border border-dashed border-line-strong px-4 py-6 text-center
                       text-sm text-muted"
            >
                No custom providers declared.
            </div>

            <div class="flex flex-col gap-2">
                <template x-for="provider in customProviders" :key="provider.name">
                    <div class="rounded-2xl border border-line px-4 py-3">
                        <div class="flex items-center gap-2">
                            <span class="h-2 w-2 shrink-0 rounded-full bg-accent"></span>
                            <p class="min-w-0 flex-1 truncate text-sm font-semibold" x-text="provider.label"></p>
                            <span
                                x-show="provider.has_key"
                                class="shrink-0 rounded-full bg-wash px-2 py-0.5 text-[10px]
                                       font-medium text-muted"
                            >key set</span>
                        </div>

                        <p class="mt-1 truncate font-mono text-[11px] text-muted" x-text="provider.url"></p>

                        <div class="mt-2 flex flex-wrap gap-1.5">
                            <template x-for="model in provider.models" :key="model.id">
                                <span class="whale-chip px-2 py-0.5 text-[11px]" x-text="model.id"></span>
                            </template>
                            <span
                                x-show="provider.models.length === 0"
                                class="whale-chip px-2 py-0.5 text-[11px] text-muted"
                            >no models kept</span>
                        </div>

                        <div class="mt-3 flex flex-wrap items-center gap-1.5">
                            <button
                                type="button"
                                class="whale-chip px-2.5 py-1"
                                x-on:click="discoverCustomModels(provider.name)"
                                x-bind:disabled="customProviderBusy[provider.name] === 'discover'"
                            >Load models</button>

                            <button
                                type="button"
                                class="whale-chip px-2.5 py-1"
                                x-on:click="syncCustomModels(provider.name)"
                                x-bind:disabled="customProviderBusy[provider.name] === 'sync'"
                            >Sync</button>

                            <button
                                type="button"
                                class="whale-chip-on px-2.5 py-1"
                                x-show="customProviderSelectionFor(provider.name).length > 0"
                                x-on:click="importCustomModels(provider.name)"
                            >
                                Keep
                                <span x-text="customProviderSelectionFor(provider.name).length"></span>
                            </button>
                        </div>

                        <div
                            class="mt-3 flex flex-col gap-1 border-t border-line pt-3"
                            x-show="(customProviderDiscovered[provider.name] ?? []).length > 0"
                        >
                            <template
                                x-for="model in (customProviderDiscovered[provider.name] ?? [])"
                                :key="model.id"
                            >
                                <label class="flex items-center gap-2 text-[13px]">
                                    <input
                                        type="checkbox"
                                        class="h-4 w-4 shrink-0 accent-[#10a37f]"
                                        :value="model.id"
                                        x-on:change="toggleCustomModel(provider.name, model.id)"
                                    >
                                    <span class="min-w-0 flex-1 truncate" x-text="model.label"></span>
                                </label>
                            </template>
                        </div>
                    </div>
                </template>
            </div>

            <h2 class="mb-2 mt-6 text-sm font-semibold">Model providers</h2>

            <div class="flex flex-col gap-2">
                <template x-for="provider in providers" :key="provider.name">
                    <div
                        class="rounded-2xl border border-line px-4 py-3"
                        :class="provider.configured ? '' : 'opacity-70'"
                    >
                        <div class="flex items-center gap-2">
                            <span
                                class="h-2 w-2 shrink-0 rounded-full"
                                :class="provider.configured ? 'bg-accent' : 'bg-faint'"
                            ></span>
                            <p class="min-w-0 flex-1 truncate text-sm font-semibold" x-text="provider.label"></p>
                            <span
                                x-show="provider.default"
                                class="shrink-0 rounded-full bg-wash px-2 py-0.5 text-[10px]
                                       font-medium text-body"
                            >default</span>
                        </div>

                        <div class="mt-2 flex flex-wrap gap-1.5">
                            <template x-for="capability in provider.capabilities" :key="capability">
                                <span class="whale-chip px-2 py-0.5 text-[11px]" x-text="capability"></span>
                            </template>

                            <span
                                x-show="provider.capabilities.length === 0"
                                class="whale-chip px-2 py-0.5 text-[11px] text-muted"
                            >no models configured</span>
                        </div>

                        <p
                            x-show="provider.configured && provider.models.length > 0"
                            class="mt-2 truncate font-mono text-[11px] text-muted"
                            x-text="provider.models.filter((model) => model.configured).map((model) => model.id).join(', ')"
                        ></p>

                        <p
                            x-show="!provider.configured"
                            class="mt-2 text-[11px] text-muted"
                        >
                            Set <span class="font-mono" x-text="provider.environment"></span> in your .env file
                        </p>
                    </div>
                </template>
            </div>
        </div>
    </div>
</div>