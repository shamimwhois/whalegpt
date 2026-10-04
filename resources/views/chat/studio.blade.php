@extends('layouts.app')

@section('title', 'Studio')

@section('content')
    <div
        x-data="studioApp({
            image: @js(route('chat.image')),
            imageEdit: @js(route('chat.image.edit')),
            video: @js(route('chat.video')),
            audio: @js(route('chat.audio')),
            vector: @js(route('chat.vector')),
            ocr: @js(route('chat.ocr')),
            workspace: @js(route('chat.workspace.index')),
            preview: @js(route('chat.workspace.preview')),
        })"
        x-init="init()"
        class="flex h-dvh flex-col overflow-hidden bg-white text-[#0d0d0d] dark:bg-[#212121] dark:text-[#ececec]"
    >
        <header class="flex h-14 shrink-0 items-center gap-2 border-b border-black/[0.08] px-3 dark:border-white/[0.12] sm:px-4">
            <a href="{{ route('chat.index') }}"
               class="flex items-center gap-2 rounded-lg px-2 py-1.5 text-sm font-medium transition
                      hover:bg-black/[0.05] dark:hover:bg-white/[0.08]">
                <span class="grid h-7 w-7 place-items-center rounded-lg bg-accent text-white">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 5h16v14H4zM4 15l4-4 4 4 3-3 5 5"/>
                    </svg>
                </span>
                <span class="hidden sm:inline">Whale Studio</span>
            </a>

            {{-- Tool switcher --}}
            <nav class="ml-1 flex items-center gap-0.5 overflow-x-auto rounded-xl bg-black/[0.04] p-1 dark:bg-white/[0.06]">
                <template x-for="tool in tools" :key="tool.id">
                    <button
                        type="button"
                        x-on:click="select(tool.id)"
                        class="whitespace-nowrap rounded-lg px-2.5 py-1.5 text-xs font-medium transition"
                        :class="active === tool.id
                            ? 'bg-white text-[#0d0d0d] shadow-sm dark:bg-[#303030] dark:text-white'
                            : 'text-[#5d5d5d] hover:text-[#0d0d0d] dark:text-[#b4b4b4] dark:hover:text-white'"
                        x-text="tool.label"
                    ></button>
                </template>
            </nav>

            <div class="min-w-0 flex-1"></div>

            <a :href="'{{ route('chat.workspace.index') }}'"
               class="hidden rounded-lg px-2.5 py-1.5 text-xs font-medium text-[#5d5d5d] transition
                      hover:bg-black/[0.05] dark:text-[#b4b4b4] dark:hover:bg-white/[0.08] sm:inline-flex">
                Open workspace
            </a>
        </header>

        {{-- Split pane: preview (left) / controls + explanation (right) --}}
        <div class="flex min-h-0 flex-1 flex-col-reverse lg:flex-row">

            {{-- Preview surface --}}
            <section class="flex min-h-0 flex-1 flex-col border-t border-black/[0.08] dark:border-white/[0.12] lg:border-r lg:border-t-0">
                <div class="flex h-10 shrink-0 items-center gap-2 border-b border-black/[0.08] px-3 dark:border-white/[0.12]">
                    <span class="text-xs font-medium text-[#5d5d5d] dark:text-[#b4b4b4]">Preview</span>
                    <div class="flex-1"></div>
                    <span x-show="resultUrl" class="text-[11px] text-[#8f8f8f]" x-text="resultLabel" style="display:none"></span>
                </div>

                <div class="whale-scroll relative min-h-0 flex-1 overflow-auto bg-[#fafafa] p-4 dark:bg-[#1a1a1a]">
                    {{-- Idle --}}
                    <template x-if="state === 'idle'">
                        <div class="grid h-full place-items-center text-center">
                            <div class="max-w-xs">
                                <p class="text-sm font-medium" x-text="current.hint"></p>
                                <p class="mt-1 text-xs text-[#8f8f8f]">Describe what you want on the right, then run it.</p>
                            </div>
                        </div>
                    </template>

                    {{-- Working --}}
                    <template x-if="state === 'working'">
                        <div class="grid h-full place-items-center">
                            <div class="flex flex-col items-center gap-3 text-[#5d5d5d] dark:text-[#b4b4b4]">
                                <svg class="h-6 w-6 animate-spin" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <circle class="opacity-25" cx="12" cy="12" r="9"/>
                                    <path class="opacity-75" stroke-linecap="round" d="M21 12a9 9 0 0 0-9-9"/>
                                </svg>
                                <span class="text-xs font-medium" x-text="current.busy"></span>
                            </div>
                        </div>
                    </template>

                    {{-- Image / edit result --}}
                    <template x-if="state === 'ready' && (active === 'image' || active === 'edit')">
                        <figure class="mx-auto max-w-2xl">
                            <img :src="resultUrl" alt="Result" class="w-full rounded-xl ring-1 ring-black/10 dark:ring-white/15">
                            <figcaption class="mt-2 flex items-center justify-between gap-3 text-xs text-[#8f8f8f]">
                                <span class="truncate" x-text="prompt"></span>
                                <a :href="resultUrl" download class="shrink-0 font-medium text-accent hover:underline">Download</a>
                            </figcaption>
                        </figure>
                    </template>

                    {{-- Video storyboard result --}}
                    <template x-if="state === 'ready' && active === 'video'">
                        <div class="mx-auto max-w-2xl">
                            <iframe :src="resultUrl" class="aspect-video w-full rounded-xl ring-1 ring-black/10 dark:ring-white/15"
                                    sandbox="allow-scripts" title="Storyboard preview"></iframe>
                            <p class="mt-2 text-xs text-[#8f8f8f]">Storyboard written to the workspace as a playable HTML file.</p>
                        </div>
                    </template>

                    {{-- Vector result --}}
                    <template x-if="state === 'ready' && active === 'vector'">
                        <div class="mx-auto max-w-2xl">
                            <iframe :src="resultUrl" class="aspect-square w-full rounded-xl bg-white ring-1 ring-black/10 dark:ring-white/15"
                                    title="Vector preview"></iframe>
                            <p class="mt-2 text-xs text-[#8f8f8f]" x-text="`Saved as ${resultPath}`"></p>
                        </div>
                    </template>

                    {{-- Audio result --}}
                    <template x-if="state === 'ready' && active === 'audio'">
                        <div class="mx-auto flex max-w-2xl flex-col items-center gap-4 py-10">
                            <audio :src="resultUrl" controls class="w-full"></audio>
                            <a :href="resultUrl" download class="text-xs font-medium text-accent hover:underline">Download audio</a>
                        </div>
                    </template>

                    {{-- OCR result --}}
                    <template x-if="state === 'ready' && active === 'ocr'">
                        <pre class="whale-scroll mx-auto max-w-2xl whitespace-pre-wrap rounded-xl bg-white p-4 text-sm
                                    ring-1 ring-black/10 dark:bg-[#242424] dark:ring-white/15"
                             x-text="ocrText"></pre>
                    </template>

                    {{-- Error --}}
                    <template x-if="state === 'error'">
                        <div class="grid h-full place-items-center">
                            <div class="max-w-sm rounded-xl bg-rose-50 p-4 text-center text-sm text-rose-600
                                        ring-1 ring-rose-200 dark:bg-rose-500/10 dark:text-rose-400 dark:ring-rose-400/20">
                                <p x-text="error"></p>
                                <button type="button" x-on:click="run()"
                                        class="mt-3 rounded-lg bg-rose-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-rose-700">
                                    Try again
                                </button>
                            </div>
                        </div>
                    </template>
                </div>
            </section>

            {{-- Controls + explanation --}}
            <aside class="flex w-full shrink-0 flex-col lg:w-[24rem]">
                <div class="whale-scroll min-h-0 flex-1 overflow-y-auto p-4">
                    <h2 class="text-sm font-semibold" x-text="current.title"></h2>
                    <p class="mt-1 text-xs leading-relaxed text-[#8f8f8f]" x-text="current.description"></p>

                    {{-- Quick-start chips: one click fills the prompt box. --}}
                    <div class="mt-3 flex flex-wrap gap-1.5">
                        <template x-for="sample in current.samples ?? []" :key="sample">
                            <button
                                type="button"
                                x-on:click="prompt = sample; $nextTick(() => $refs.studioPrompt?.focus())"
                                x-text="sample"
                                class="rounded-full border border-black/[0.08] px-2.5 py-1 text-[11px]
                                       text-[#5d5d5d] transition hover:bg-black/[0.05]
                                       dark:border-white/[0.14] dark:text-[#b4b4b4]
                                       dark:hover:bg-white/[0.08]"
                            ></button>
                        </template>
                    </div>

                    {{-- Prompt --}}
                    <div class="mt-4 flex items-center justify-between">
                        <label for="studio-prompt" class="block text-xs font-medium text-[#5d5d5d] dark:text-[#b4b4b4]">
                            Prompt
                        </label>
                        <span class="text-[10px] tabular-nums text-[#8f8f8f]">
                            <span x-text="prompt.length"></span>/2000
                        </span>
                    </div>
                    <textarea
                        id="studio-prompt"
                        x-ref="studioPrompt"
                        x-model="prompt"
                        x-on:input="autoGrow($event)"
                        x-on:keydown.ctrl.enter.prevent="run()"
                        x-on:keydown.meta.enter.prevent="run()"
                        rows="3"
                        maxlength="2000"
                        :placeholder="current.placeholder"
                        class="studio-input mt-1.5 max-h-64 w-full resize-none overflow-y-auto rounded-xl border
                               border-black/[0.1] bg-white p-3 text-sm leading-6
                               placeholder:text-[#8f8f8f]
                               focus:border-accent focus:outline-none focus:ring-2 focus:ring-accent/20
                               dark:border-white/[0.14] dark:bg-[#303030]"
                    ></textarea>
                    <p class="mt-1 text-[10px] text-[#8f8f8f]">
                        <kbd class="rounded border border-black/[0.1] px-1 py-0.5 font-sans dark:border-white/[0.15]">Ctrl</kbd>
                        +
                        <kbd class="rounded border border-black/[0.1] px-1 py-0.5 font-sans dark:border-white/[0.15]">Enter</kbd>
                        to run
                    </p>

                    {{-- Style: appended to the prompt for image, video and
                         vector work so one control covers every generator. --}}
                    <template x-if="active === 'image' || active === 'video' || active === 'vector'">
                        <div class="mt-4">
                            <x-chat.style-chips />
                        </div>
                    </template>

                    {{-- Source image (edit / ocr) --}}
                    <template x-if="active === 'edit' || active === 'ocr'">
                        <div class="mt-4">
                            <label class="block text-xs font-medium text-[#5d5d5d] dark:text-[#b4b4b4]">Source image</label>
                            <input type="file" accept="image/*" x-ref="sourceImage"
                                   x-on:change="sourceFile = $event.target.files[0] ?? null"
                                   class="mt-1.5 block w-full text-xs text-[#8f8f8f]
                                          file:mr-3 file:rounded-lg file:border-0 file:bg-black/[0.06] file:px-3 file:py-1.5
                                          file:text-xs file:font-medium dark:file:bg-white/[0.1] dark:file:text-white">
                        </div>
                    </template>

                    {{-- Image size --}}
                    <template x-if="active === 'image' || active === 'edit'">
                        <div class="mt-4">
                            <label class="block text-xs font-medium text-[#5d5d5d] dark:text-[#b4b4b4]">Aspect ratio</label>
                            <div class="mt-1.5 flex gap-1.5">
                                <template x-for="size in ['square', 'portrait', 'landscape']" :key="size">
                                    <button type="button" x-on:click="options.size = size"
                                            class="flex-1 rounded-lg border px-2 py-1.5 text-xs font-medium capitalize transition"
                                            :class="options.size === size
                                                ? 'border-accent bg-accent/10 text-accent'
                                                : 'border-black/[0.1] text-[#5d5d5d] dark:border-white/[0.14] dark:text-[#b4b4b4]'"
                                            x-text="size"></button>
                                </template>
                            </div>
                        </div>
                    </template>

                    {{-- Video options --}}
                    <template x-if="active === 'video'">
                        <div class="mt-4 grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-medium text-[#5d5d5d] dark:text-[#b4b4b4]">Frames</label>
                                <input type="number" min="2" max="6" x-model.number="options.frames"
                                       class="mt-1.5 w-full rounded-lg border border-black/[0.1] bg-white px-2.5 py-1.5 text-sm
                                              dark:border-white/[0.14] dark:bg-[#303030]">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-[#5d5d5d] dark:text-[#b4b4b4]">Seconds each</label>
                                <input type="number" min="0.5" max="5" step="0.5" x-model.number="options.seconds"
                                       class="mt-1.5 w-full rounded-lg border border-black/[0.1] bg-white px-2.5 py-1.5 text-sm
                                              dark:border-white/[0.14] dark:bg-[#303030]">
                            </div>
                        </div>
                    </template>

                    {{-- Vector path --}}
                    <template x-if="active === 'vector'">
                        <div class="mt-4">
                            <label class="block text-xs font-medium text-[#5d5d5d] dark:text-[#b4b4b4]">Output path</label>
                            <input type="text" x-model="options.path" placeholder="art/logo.svg"
                                   class="mt-1.5 w-full rounded-lg border border-black/[0.1] bg-white px-2.5 py-1.5 font-mono text-xs
                                          dark:border-white/[0.14] dark:bg-[#303030]">
                        </div>
                    </template>

                    {{-- Voice --}}
                    <template x-if="active === 'audio'">
                        <div class="mt-4">
                            <label class="block text-xs font-medium text-[#5d5d5d] dark:text-[#b4b4b4]">Voice</label>
                            <div class="mt-1.5 flex gap-1.5">
                                <template x-for="voice in ['female', 'male']" :key="voice">
                                    <button type="button" x-on:click="options.voice = voice"
                                            class="flex-1 rounded-lg border px-2 py-1.5 text-xs font-medium capitalize transition"
                                            :class="options.voice === voice
                                                ? 'border-accent bg-accent/10 text-accent'
                                                : 'border-black/[0.1] text-[#5d5d5d] dark:border-white/[0.14] dark:text-[#b4b4b4]'"
                                            x-text="voice"></button>
                                </template>
                            </div>
                        </div>
                    </template>

                    <button
                        type="button"
                        x-on:click="run()"
                        :disabled="state === 'working' || (!prompt.trim() && !(active === 'ocr'))"
                        class="mt-5 w-full rounded-xl bg-[#0d0d0d] px-4 py-2.5 text-sm font-medium text-white transition
                               hover:bg-black/80 disabled:cursor-not-allowed disabled:opacity-40
                               dark:bg-white dark:text-[#0d0d0d] dark:hover:bg-[#ececec]"
                    >
                        <span x-text="current.action"></span>
                    </button>

                    <p x-show="error" x-text="error" class="mt-3 text-xs text-rose-500" style="display:none"></p>
                </div>
            </aside>
        </div>
    </div>

    @push('scripts')
    <script>
        function studioApp(routes) {
            return {
                routes,
                // Image styles: what the server will append to the prompt.
                imageStyle: 'any',
                imageStyles: @js(\App\Ai\ImageStyle::toArray()),
                active: 'image',
                state: 'idle',
                prompt: '',
                resultUrl: null,
                resultPath: null,
                resultLabel: '',
                ocrText: '',
                error: null,
                sourceFile: null,
                options: { size: 'landscape', frames: 4, seconds: 1.5, path: '', voice: 'female' },

                tools: [
                    {
                        id: 'image',
                        label: 'Image',
                        title: 'Generate an image',
                        description: 'Turn a description into a picture. Needs an image-capable provider (OpenAI, Gemini or xAI).',
                        placeholder: 'A whale breaching at sunset, cinematic lighting',
                        hint: 'Describe an image to generate.',
                        busy: 'Painting your image…',
                        action: 'Generate image',
                        samples: [
                            'A whale breaching at sunset',
                            'Minimal isometric server rack, flat colours',
                            'Technical diagram of a request lifecycle',
                        ],
                    },
                    {
                        id: 'edit',
                        label: 'Edit image',
                        title: 'Edit an image',
                        description: 'Upload a picture and describe the change. The source image is sent to the model as reference.',
                        placeholder: 'Make the sky purple and add stars',
                        hint: 'Upload an image and describe the change.',
                        busy: 'Editing your image…',
                        action: 'Edit image',
                    },
                    {
                        id: 'video',
                        label: 'Video',
                        title: 'Build a video storyboard',
                        description: 'There is no text-to-video provider in the SDK, so this generates a sequence of consistent frames and writes a playable HTML storyboard into your workspace.',
                        placeholder: 'A drone flight over a misty forest at dawn',
                        hint: 'Describe a scene to storyboard.',
                        busy: 'Generating frames…',
                        action: 'Build storyboard',
                    },
                    {
                        id: 'vector',
                        label: 'Vector',
                        title: 'Create vector art',
                        description: 'An agent writes real SVG markup into your workspace — scalable, editable and openable in the code editor.',
                        placeholder: 'A minimal line-art whale logo, two colours',
                        hint: 'Describe the vector art to create.',
                        busy: 'Drawing vectors…',
                        action: 'Create SVG',
                        samples: [
                            'A minimal line-art whale logo',
                            'A set of five navigation icons',
                            'A layered architecture diagram',
                        ],
                    },
                    {
                        id: 'audio',
                        label: 'Audio',
                        title: 'Generate speech',
                        description: 'Turn text into speech. Needs a TTS-capable provider (OpenAI, ElevenLabs or Mistral).',
                        placeholder: 'Welcome to Whale. Let me walk you through it.',
                        hint: 'Type the text to speak.',
                        busy: 'Synthesising speech…',
                        action: 'Generate audio',
                        samples: [
                            'Welcome to Whale.',
                            'Here is your three-step summary.',
                            'The build finished successfully.',
                        ],
                    },
                    {
                        id: 'ocr',
                        label: 'OCR',
                        title: 'Read text from an image',
                        description: 'Extract every piece of text in an image verbatim, using a vision-capable model.',
                        placeholder: 'Optional: describe what to look for',
                        hint: 'Upload an image to extract text from.',
                        busy: 'Reading the image…',
                        action: 'Extract text',
                    },
                ],

                get current() {
                    return this.tools.find((tool) => tool.id === this.active);
                },

                init() {
                    // Nothing to load up-front; the pane starts in its idle state.
                },

                // Grow the prompt box with its content, capped so it never
                // pushes the tool list off-screen.
                autoGrow(event) {
                    const element = event.target;

                    element.style.height = 'auto';
                    element.style.height = `${Math.min(element.scrollHeight, 256)}px`;
                },

                select(id) {
                    this.active = id;
                    this.state = 'idle';
                    this.resultUrl = null;
                    this.resultPath = null;
                    this.error = null;
                    this.ocrText = '';
                },

                workspaceParam() {
                    return window.whaleWorkspaceId();
                },

                async run() {
                    this.error = null;
                    this.state = 'working';
                    this.resultUrl = null;
                    this.resultPath = null;

                    try {
                        const handler = {
                            image: () => this.runImage(),
                            edit: () => this.runImageEdit(),
                            video: () => this.runVideo(),
                            vector: () => this.runVector(),
                            audio: () => this.runAudio(),
                            ocr: () => this.runOcr(),
                        }[this.active];

                        await handler();
                    } catch (error) {
                        this.state = 'error';
                        this.error = error?.message || 'Something went wrong.';
                    }
                },

                async postJson(url, body) {
                    const response = await fetch(url, {
                        method: 'POST',
                        headers: { 'Accept': 'application/json', 'Content-Type': 'application/json' },
                        body: JSON.stringify({ ...body, workspace: this.workspaceParam() }),
                    });

                    const payload = await response.json().catch(() => null);

                    if (!response.ok) {
                        throw new Error(payload?.message || 'The request failed.');
                    }

                    return payload;
                },

                async runImage() {
                    const payload = await this.postJson(this.routes.image, {
                        prompt: this.prompt.trim(),
                        size: this.options.size,
                        style: this.imageStyle,
                    });

                    this.resultUrl = payload.url;
                    this.resultLabel = 'Generated';
                    this.state = 'ready';
                },

                async runImageEdit() {
                    if (!this.sourceFile) {
                        throw new Error('Choose a source image first.');
                    }

                    const body = new FormData();
                    body.append('image', this.sourceFile);
                    body.append('prompt', this.prompt.trim());
                    body.append('size', this.options.size);
                    body.append('style', this.imageStyle);
                    body.append('workspace', this.workspaceParam());

                    const response = await fetch(this.routes.imageEdit, {
                        method: 'POST',
                        headers: { 'Accept': 'application/json' },
                        body,
                    });

                    const payload = await response.json().catch(() => null);

                    if (!response.ok) {
                        throw new Error(payload?.message || 'The request failed.');
                    }

                    this.resultUrl = payload.url;
                    this.resultLabel = 'Edited';
                    this.state = 'ready';
                },

                async runVideo() {
                    const payload = await this.postJson(this.routes.video, {
                        prompt: this.prompt.trim(),
                        frames: this.options.frames,
                        seconds_per_frame: this.options.seconds,
                        style: this.imageStyle,
                    });

                    this.resultUrl = payload.url;
                    this.resultPath = payload.path;
                    this.resultLabel = 'Storyboard';
                    this.state = 'ready';
                },

                async runVector() {
                    const payload = await this.postJson(this.routes.vector, {
                        prompt: this.prompt.trim(),
                        path: this.options.path || undefined,
                        style: this.imageStyle,
                    });

                    this.resultPath = payload.path;
                    this.resultUrl = payload.url ?? this.previewUrl(payload.path);
                    this.resultLabel = payload.path;
                    this.state = 'ready';
                },

                async runAudio() {
                    const payload = await this.postJson(this.routes.audio, {
                        text: this.prompt.trim(),
                        voice: this.options.voice,
                    });

                    this.resultUrl = payload.url;
                    this.resultLabel = 'Speech';
                    this.state = 'ready';
                },

                async runOcr() {
                    if (!this.sourceFile) {
                        throw new Error('Choose an image first.');
                    }

                    const body = new FormData();
                    body.append('image', this.sourceFile);
                    body.append('workspace', this.workspaceParam());

                    const response = await fetch(this.routes.ocr, {
                        method: 'POST',
                        headers: { 'Accept': 'application/json' },
                        body,
                    });

                    const payload = await response.json().catch(() => null);

                    if (!response.ok) {
                        throw new Error(payload?.message || 'The request failed.');
                    }

                    this.ocrText = payload.text ?? '';
                    this.resultLabel = 'Extracted';
                    this.state = 'ready';
                },

                previewUrl(path) {
                    const url = new URL(this.routes.preview, window.location.origin);
                    url.searchParams.set('path', path);
                    url.searchParams.set('workspace', this.workspaceParam());

                    return url.toString();
                },
            };
        }
    </script>
    @endpush
@endsection
