@extends('layouts.app')

@section('title', 'AI Assistant')

@section('content')

    <div
        x-data="chatApp({
            send: @js(route('chat.send')),
            image: @js(route('chat.image')),
            imageEdit: @js(route('chat.image.edit')),
            audio: @js(route('chat.audio')),
            ocr: @js(route('chat.ocr')),
            vector: @js(route('chat.vector')),
            video: @js(route('chat.video')),
            models: @js(route('chat.models')),
            localModels: @js(route('chat.local-models')),
            customProviders: @js(route('chat.custom-providers')),
            capabilities: @js(route('chat.capabilities')),
            enhance: @js(route('chat.enhance')),
            history: @js(route('chat.history.index')),
            workspace: @js(route('chat.workspace')),
            mentions: @js(route('chat.workspace.mentions')),
            boardUpload: @js(route('chat.workspace.upload')),
            studio: @js(route('chat.studio')),
        })"
        x-init="init()"
        class="flex h-dvh overflow-hidden bg-white text-[#0d0d0d] dark:bg-[#212121] dark:text-[#ececec]"
    >
        <x-chat.sidebar />

        <div class="flex min-w-0 flex-1 flex-col">

            <x-chat.header />

            {{-- The conversation is the page's primary content, so the scroller
                 is the main landmark. The header above it stays a banner
                 because a <header> only stops being one when it is nested
                 inside a <main>. --}}
            <main
                x-ref="scroller"
                x-on:scroll="onScroll()"
                x-on:click="handleCodeBlockClick($event)"
                aria-label="Conversation"
                class="whale-scroll flex-1 overflow-y-auto overscroll-contain px-4 py-6 sm:px-6 lg:px-8"
            >
                <div class="mx-auto flex min-h-full max-w-3xl flex-col">

                    {{-- Empty state: just the greeting. The starter prompts moved
                         into the composer as a rotating placeholder, so there is
                         one thing to click and the hint sits where typing starts. --}}
                    <div
                        x-show="messages.length === 0"
                        class="flex flex-1 flex-col items-center justify-center gap-8 py-10"
                    >
                        <div class="text-center">
                            <p class="text-2xl font-semibold tracking-[-0.02em]">What can I help with?</p>
                            <p class="mt-1.5 text-sm text-[#5d5d5d] dark:text-[#b4b4b4]">
                                Ask anything, attach a file, or generate an image.
                            </p>
                        </div>
                    </div>

                    <div
                        class="whale-transcript flex flex-col"
                        x-show="messages.length > 0"
                        style="display:none"
                    >
                        <div class="flex items-center gap-3">
                            <span class="h-px flex-1 bg-black/[0.08] dark:bg-white/[0.12]"></span>
                            <span class="text-[11px] font-medium text-[#8f8f8f]">Today</span>
                            <span class="h-px flex-1 bg-black/[0.08] dark:bg-white/[0.12]"></span>
                        </div>

                        <template x-for="msg in messages" :key="msg.id">
                            @include('chat.partials.message')
                        </template>

                        <x-chat.typing />
                    </div>
                </div>
            </main>

            {{-- A failure has to say so where the user is already looking. This
                 clears itself rather than demanding a click, so a bar cannot be
                 left stranded over the composer by a transient network error. --}}
            <div
                x-show="notice"
                class="mx-4 mb-3 flex items-start gap-3 rounded-xl border border-rose-500/25 bg-rose-500/[0.07] px-3.5 py-2.5 text-sm text-rose-600 dark:border-rose-400/25 dark:bg-rose-400/10 dark:text-rose-400"
                role="alert"
                style="display:none"
            >
                <p class="flex-1 leading-relaxed" x-text="notice"></p>

                <button
                    type="button"
                    x-on:click="dismissNotice()"
                    aria-label="Dismiss this message"
                    class="-m-1 shrink-0 rounded-md p-1 opacity-70 transition-opacity hover:opacity-100 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-current"
                >
                    <x-ui.icon name="close" class="h-4 w-4" />
                </button>
            </div>

            {{-- Status changes are announced rather than drawn: "Thinking…" and
                 "Responding…" move too quickly to be worth a bar of their own,
                 but they are exactly what a screen reader would otherwise miss. --}}
            <div class="sr-only" aria-live="polite" x-text="announcement"></div>

            <x-chat.composer />

            <x-chat.sandbox />

            <x-chat.settings />

            {{-- Ctrl/Cmd+K from anywhere on the page, including the composer. --}}
            <div
                x-on:keydown.window="if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k') { event.preventDefault(); togglePalette(); }"
                x-on:keydown.escape.window="if (palette.open) { closePalette(); }"
            ></div>

            <x-ui.command-palette />

            <x-chat.project-dialog />

            <x-ui.confirm-dialog />

            <x-chat.board />
        </div>
    </div>

    @push('head')
    {{-- ChatGPT-style rendering stack: markdown, sanitization, syntax highlighting --}}
    <script src="https://cdn.jsdelivr.net/npm/marked@12.0.2/marked.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/dompurify@3.2.4/dist/purify.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/highlight.js@11.9.0/styles/github-dark.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/gh/highlightjs/cdn-release@11.9.0/build/highlight.min.js"></script>
    @endpush

    @push('scripts')
    <script>
        function chatApp(routes) {
            let idCounter = 0;
            let sandboxTimer = null;
            let markdownRenderer = null;

            const MODEL_STORAGE_KEY = 'whale.model';
            const DEPTH_STORAGE_KEY = 'whale.depth';
            const EFFORT_STORAGE_KEY = 'whale.thinking';
            const LENGTH_STORAGE_KEY = 'whale.length';
            const SIDEBAR_COLLAPSED_STORAGE_KEY = 'whale.sidebar.collapsed';
            const SIDEBAR_WIDTH_STORAGE_KEY = 'whale.sidebar.width';
            const HISTORY_LAYOUT_STORAGE_KEY = 'whale.history.layout';
            const READING_SCALE_STORAGE_KEY = 'whale.reading.scale';
            const DENSITY_STORAGE_KEY = 'whale.transcript.density';
            const REDUCE_MOTION_STORAGE_KEY = 'whale.reduce.motion';

            // Starter prompts, shown in the composer as a rotating placeholder.
            // They used to be buttons under the greeting; a hint that sits
            // where typing starts costs no clicks and cannot be sent by
            // accident, which a prefilled prompt could.
            const STARTER_PROMPTS = [
                'Summarize this thread',
                'Write a migration',
                'Explain queues',
            ];

            // How long each hint holds before the next one takes its place.
            const STARTER_PROMPT_INTERVAL = 4000;

            // The width limits are shared by the stored value, the drag handler
            // and the reset button, so the sidebar has one legal range rather
            // than three that can disagree.
            const SIDEBAR_MIN_WIDTH = 200;
            const SIDEBAR_MAX_WIDTH = 380;
            const SIDEBAR_DEFAULT_WIDTH = 260;

            const escapeText = (value) => String(value ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;');

            const SANDBOX_SAMPLE = `<main style="font:14px/1.6 system-ui,sans-serif;padding:16px">
                <h1 style="margin:0 0 8px">Sandbox preview</h1>
                <p style="margin:0 0 12px">Heading, paragraph, link, list and button samples.</p>
                <p><a href="#">A link</a> and <strong>bold text</strong>.</p>
                <ul style="margin:12px 0"><li>First item</li><li>Second item</li></ul>
                <button style="padding:6px 12px;border-radius:8px;border:1px solid #10a37f;background:#10a37f;color:#fff">Button</button>
            </main>`;

            // marked >= 5 hands the renderer a token object; older builds pass
            // positional arguments. Both shapes are supported here.
            const buildRenderer = () => {
                if (markdownRenderer || typeof marked === 'undefined') {
                    return markdownRenderer;
                }

                const renderer = new marked.Renderer();

                renderer.code = (token, infoString) => {
                    const isToken = token !== null && typeof token === 'object';
                    const code = isToken ? (token.text ?? '') : (token ?? '');
                    const rawLang = isToken ? (token.lang ?? '') : (infoString ?? '');
                    const lang = rawLang.trim().split(/\s+/)[0] || 'text';
                    const known = typeof hljs !== 'undefined' && hljs.getLanguage(lang);
                    const body = known
                        ? hljs.highlight(code, { language: lang }).value
                        : escapeText(code);
                    const label = lang.replace(/[^a-zA-Z0-9+#._-]/g, '') || 'text';

                    return `<div class="code-block" data-lang="${label}">`
                        + '<div class="code-block__bar">'
                        + `<span>${label}</span>`
                        + '<span class="code-block__actions">'
                        + '<button type="button" data-code-action="copy">Copy</button>'
                        + '<button type="button" data-code-action="sandbox" data-code-lang="' + label + '">Sandbox</button>'
                        + '</span></div>'
                        + `<pre><code class="hljs language-${label}">${body}</code></pre>`
                        + '</div>';
                };

                markdownRenderer = renderer;

                return markdownRenderer;
            };

            return {
                routes,
                endpoint: routes.send,
                mobileSidebar: false,

                // Sidebar shape. All three are user preferences, so they are
                // restored on load and persisted on change rather than being
                // derived from the viewport — a desktop user who collapses the
                // sidebar should not have it spring back on refresh.
                sidebarCollapsed: false,
                sidebarWidth: 260,
                historyLayout: 'list',

                // Reading preferences. These write CSS custom properties and one
                // class on the page root rather than binding to individual
                // elements, so a change repaints the whole transcript in one go
                // instead of touching a hundred nodes.
                readingScale: 1,
                transcriptDensity: 1.5,
                reduceMotion: false,

                draft: '',
                starterPromptIndex: 0,
                starterPromptTimer: null,
                searchQuery: '',
                messages: [],
                pendingFiles: [],
                mode: 'chat',
                depth: 'fast',
                thinking: 'medium',
                web: false,
                deepSearch: false,
                effortOpen: false,
                // The image look to ask for; "any" sends the prompt untouched.
                imageStyle: 'any',
                imageStyles: [
                    { id: 'any', label: 'Any', description: 'No style added — the prompt is sent exactly as written.', glyph: '⋯' },
                ],
                // The draw and scratch board.
                board: {
                    open: false,
                    initialised: false,
                    drawing: false,
                    tool: 'brush',
                    colour: '#0d0d0d',
                    size: 6,
                    prompt: '',
                    resultUrl: null,
                    busy: false,
                    error: null,
                    history: [],

                    // Shape gestures need the drag start point and a scratch
                    // layer; neither is a template-safe default.
                    startX: 0,
                    startY: 0,
                    preview: null,

                    /**
                     * Whether the current tool is dragged rather than painted.
                     *
                     * A getter rather than a flag, so it can never disagree with
                     * `tool` after a selection change.
                     */
                    get isShape() {
                        return ['line', 'rect', 'ellipse'].includes(this.tool);
                    },

                    /**
                     * Whether the tool paints freehand.
                     */
                    get isFreehand() {
                        return ['brush', 'eraser'].includes(this.tool);
                    },
                },
                attachMenuOpen: false,
                pickerOpen: false,
                settingsOpen: false,
                modeOpen: false,
                exportOpen: false,
                modelFilter: 'all',
                enhancing: false,

                // Response-shaping dropdowns. All three now belong to the
                // composer alone; the header used to open `depthOpen` too, which
                // is why both menus appeared at once.
                depthOpen: false,
                effortOpen: false,
                lengthOpen: false,
                length: 'auto',
                lengthIndex: 0,
                enhancedDraft: null,
                enhancedOriginal: null,

                // The specialist the assistant may delegate to for this thread.
                // The server is the source of truth and replaces these on load.
                modes: [
                    { id: 'chat', label: 'Chat', description: 'Everyday conversation, no tools.' },
                    { id: 'code', label: 'Code', description: 'Build and edit files in your workspace.' },
                    { id: 'research', label: 'Research', description: 'Current, source-backed answers from the web.' },
                    { id: 'deep-search', label: 'Deep search', description: 'Multi-query research that cross-checks sources.' },
                    { id: 'study', label: 'Study', description: 'Explanations, study plans and flashcards.' },
                    { id: 'security', label: 'Security study', description: 'Ethical-hacking study for authorised, defensive work.' },
                    { id: 'art', label: 'Art', description: 'Create and edit SVG vector artwork.' },
                ],

                depths: [
                    { id: 'deep', label: 'Deep thinking', description: 'Reasoning models, more tool steps, thorough answers.' },
                    { id: 'fast', label: 'Fast', description: 'Balanced speed and quality. The default.' },
                    { id: 'super', label: 'Super fast', description: 'Fewest steps, lowest latency, terse answers.' },
                ],

                efforts: [
                    { id: 'low', label: 'Low', description: 'Minimal deliberation. Quickest first token.' },
                    { id: 'medium', label: 'Medium', description: 'Balanced reasoning. The default.' },
                    { id: 'high', label: 'High', description: 'Work through hard problems properly.' },
                    { id: 'xhigh', label: 'Extra high', description: 'Maximum deliberation for the hardest questions.' },
                ],

                lengths: [
                    { id: 'auto', label: 'Auto', description: 'The provider decides how long to write.' },
                    { id: 'short', label: 'Short', description: 'A few sentences. Good for quick answers.' },
                    { id: 'medium', label: 'Medium', description: 'A paragraph or two with room to explain.' },
                    { id: 'long', label: 'Long', description: 'As long as it takes, including code and detail.' },
                ],

                // Export formats, rendered as label + file extension in the header menu.
                formats: [
                    ['txt', 'Plain text'],
                    ['md', 'Markdown'],
                    ['html', 'HTML'],
                    ['json', 'JSON'],
                    ['pdf', 'PDF'],
                ],

                // @-mentions and slash commands in the composer.
                mentionCatalog: [],
                mentionFiles: [],
                mentionOpen: false,
                mentionIndex: 0,
                mentionQuery: '',
                mentionRange: null,
                slashOpen: false,
                slashIndex: 0,
                slashQuery: '',
                slashRange: null,
                selectionStart: 0,
                selectionEnd: 0,
                providers: [],
                providerName: null,
                modelName: null,
                localModels: [],
                modelsPath: '',
                // Files in the models directory that look like models but cannot
                // be read, and the runtimes the detected models would use.
                unreadableModels: [],
                runtimeHealth: {},
                // Custom providers declared in WHALE_CUSTOM_PROVIDERS. `selection`
                // holds the ids ticked in the discovered list, keyed by provider,
                // so an import sends only what was actually chosen.
                customProviders: [],
                customProviderDiscovered: {},
                customProviderSelection: {},
                customProviderBusy: {},
                ocrPending: false,
                fileError: null,
                editingId: null,
                editDraft: '',
                sandbox: { open: false, lang: 'html', code: '', original: '', dirty: false, previewNote: '' },

                // Sidebar management (projects + history).
                projects: [],
                conversations: [],
                archived: [],
                activeConversationId: null,
                shareUrl: null,
                shareUrlFor: null,
                projectDialogOpen: false,
                projectDraft: '',
                draggedConversationId: null,

                // The one destructive-action dialog, described as data so the
                // markup stays a static template. See components/ui/confirm-dialog.
                confirmDialog: null,

                // Which row's overflow menu is open, and what it was opened for.
                // Keyed rather than a single flag so a menu closes when another
                // one opens instead of two overlapping.
                menuFor: null,
                renamingId: null,
                renameDraft: '',

                // The command palette. Modelled on the workspace IDE's palette
                // rather than a new shape: one query, one highlighted row,
                // arrow keys to move, Enter to run. Commands carry their own
                // `run` so the list is data, not a switch statement in a click
                // handler that would have to grow a branch per command.
                palette: { open: false, query: '', index: 0 },

                // Projects rename in place too, but they are not part of the
                // chat list, so they get their own key rather than sharing the
                // chat one and fighting over it.
                renamingProjectId: null,
                projectRenameDraft: '',

                // The resizer listens on the window rather than the handle,
                // because a drag that outruns the cursor would otherwise be
                // cut off the moment the pointer left the 4px handle.
                resizingSidebar: false,

                // idle | thinking | responding | stopped | error
                status: 'idle',
                isStreaming: false,
                abortController: null,
                isPinnedToBottom: true,
                lastError: null,

                // Every failure below writes lastError, but nothing rendered it:
                // a chat that would not open, a send that failed and a rename
                // that was rejected were all indistinguishable from doing
                // nothing. This is the visible half of it.
                notice: null,
                noticeTimer: null,

                get isTyping() {
                    return this.status === 'thinking';
                },

                get selectedProvider() {
                    return this.providers.find((provider) => provider.name === this.providerName) ?? null;
                },

                // The header reads this, so it always names the model that will
                // actually answer instead of a hardcoded label.
                modelLabel() {
                    const model = this.selectedProvider?.models.find((entry) => entry.id === this.modelName);

                    if (model) {
                        return model.label;
                    }

                    return this.selectedProvider?.label ?? 'Select a model';
                },

                configuredProviderCount() {
                    return this.providers.filter((provider) => provider.configured).length;
                },

                // ---- Model type filter ---------------------------------------
                // "Type" is how a model answers, not which vendor owns it:
                // a reasoning model is a different choice from a fast one.
                modelFilterOptions() {
                    return [
                        { id: 'all', label: 'All' },
                        { id: 'reasoning', label: 'Reasoning' },
                        { id: 'fast', label: 'Fast' },
                        { id: 'image', label: 'Image' },
                        { id: 'media', label: 'Media' },
                    ];
                },

                modelVisible(model) {
                    if (!model.configured) {
                        return false;
                    }

                    if (this.modelFilter === 'all') {
                        return true;
                    }

                    // A media model filters by what it makes rather than by how it
                    // chats, so an image checkpoint shows under Image and Media
                    // rather than nowhere at all.
                    if (this.modelFilter === 'media') {
                        return model.media === true;
                    }

                    if (model.media === true) {
                        return (model.capabilities ?? []).includes(this.modelFilter);
                    }

                    return model.type === this.modelFilter;
                },

                // Hide a provider heading that has nothing left to show under
                // the active type filter.
                providerVisible(provider) {
                    if (!provider.configured) {
                        return false;
                    }

                    if (this.modelFilter === 'all') {
                        return true;
                    }

                    return provider.models.some((model) => this.modelVisible(model));
                },

                visibleModelCount() {
                    return this.providers
                        .filter((provider) => this.providerVisible(provider))
                        .reduce((total, provider) => total + provider.models.filter((model) => this.modelVisible(model)).length, 0);
                },

                // ---- Header labels ---------------------------------------------
                modeLabel() {
                    return this.modes.find((option) => option.id === this.mode)?.label ?? this.mode;
                },

                depthLabel() {
                    return this.depths.find((option) => option.id === this.depth)?.label ?? this.depth;
                },

                setDepth(id) {
                    this.depth = id;
                    this.store(DEPTH_STORAGE_KEY, id);
                },

                effortLabel() {
                    return this.efforts.find((option) => option.id === this.thinking)?.label ?? this.thinking;
                },

                setEffort(id) {
                    this.thinking = id;
                    this.store(EFFORT_STORAGE_KEY, id);
                },

                // The slider carries an index, because a range input speaks in
                // numbers and the setting is a named tier. Converting in one
                // place keeps the tier list the single source of truth.
                lengthLabel() {
                    return this.lengths[this.lengthIndex]?.label ?? 'Auto';
                },

                setLengthIndex(index) {
                    const clamped = Math.max(0, Math.min(this.lengths.length - 1, Number(index) || 0));

                    this.lengthIndex = clamped;
                    this.length = this.lengths[clamped]?.id ?? 'auto';
                    this.store(LENGTH_STORAGE_KEY, this.length);
                },

                syncLengthIndex() {
                    const index = this.lengths.findIndex((option) => option.id === this.length);

                    this.lengthIndex = index === -1 ? 0 : index;
                },

                lengthDescription() {
                    return this.lengths[this.lengthIndex]?.description ?? '';
                },

                // Persisting a preference must never break the page it sits on.
                store(key, value) {
                    try {
                        localStorage.setItem(key, value);
                    } catch (error) {
                        // Storage-disabled browsers keep it for this page only.
                    }
                },

                read(key) {
                    try {
                        return localStorage.getItem(key);
                    } catch (error) {
                        return null;
                    }
                },

                // ---- Composer suggestions: @-mentions and / commands ------------
                // Everything the mention picker can offer, both files and agents.
                mentionItems() {
                    return [
                        ...this.mentionCatalog.map((agent) => ({ ...agent, group: 'agent' })),
                        ...this.mentionFiles.map((file) => ({ ...file, group: 'file' })),
                    ];
                },

                mentionMatches() {
                    const query = this.mentionQuery.toLowerCase();

                    return this.mentionItems()
                        .filter((item) => !query
                            || item.id.toLowerCase().includes(query)
                            || (item.label ?? '').toLowerCase().includes(query))
                        .slice(0, 8);
                },

                slashCommands() {
                    return [
                        { id: 'new', icon: '+', label: '/new', description: 'Start a new chat.' },
                        { id: 'clear', icon: '⌫', label: '/clear', description: 'Clear this transcript.' },
                        { id: 'code', icon: '{}', label: '/code', description: 'Switch to coding mode.' },
                        { id: 'research', icon: '?', label: '/research', description: 'Switch to research mode.' },
                        { id: 'deep-search', icon: '∞', label: '/deep-search', description: 'Switch to deep-search mode.' },
                        { id: 'study', icon: 'A', label: '/study', description: 'Switch to study mode.' },
                        { id: 'security', icon: '!', label: '/security', description: 'Switch to ethical-hacking study mode.' },
                        { id: 'art', icon: '◊', label: '/art', description: 'Switch to vector-art mode.' },
                        { id: 'image', icon: '▣', label: '/image', description: 'Generate an image from a prompt.' },
                        { id: 'art', icon: '◊', label: '/art', description: 'Open the draw and scratch board, then render it.' },
                        { id: 'board', icon: '✎', label: '/board', description: 'Open the draw and scratch board.' },
                        { id: 'deep', icon: '✦', label: '/deep', description: 'Set response depth to deep thinking.' },
                        { id: 'fast', icon: '»', label: '/fast', description: 'Set response depth to fast.' },
                        { id: 'super', icon: '≫', label: '/super', description: 'Set response depth to super fast.' },
                        { id: 'enhance', icon: '✧', label: '/enhance', description: 'Rewrite the current draft.' },
                        { id: 'export', icon: '↓', label: '/export', description: 'Export the conversation as markdown.' },
                        { id: 'workspace', icon: '▤', label: '/workspace', description: 'Open the file workspace.' },
                        { id: 'studio', icon: '◫', label: '/studio', description: 'Open the media studio.' },
                        { id: 'help', icon: 'i', label: '/help', description: 'Show what Whale can do.' },
                    ];
                },

                slashMatches() {
                    const query = this.slashQuery.toLowerCase();

                    return this.slashCommands()
                        .filter((command) => !query || command.id.startsWith(query))
                        .slice(0, 9);
                },

                // Recompute which suggestion panel should be open from the text
                // before the caret. Called on every input event.
                syncSuggestions(event) {
                    const element = event.target;
                    const before = element.value.slice(0, element.selectionStart ?? 0);

                    this.syncSelection(event);

                    // A slash command is only offered at the very start of a line.
                    const slash = before.match(/(?:^|\n)\/([\w-]*)$/);

                    if (slash) {
                        this.slashQuery = slash[1];
                        this.slashRange = { start: (element.selectionStart ?? 0) - slash[0].length, end: element.selectionStart ?? 0 };
                        this.slashOpen = true;
                        this.slashIndex = 0;
                        this.mentionOpen = false;

                        return;
                    }

                    this.slashOpen = false;

                    const mention = before.match(/@([\w./-]*)$/);

                    if (mention) {
                        this.mentionQuery = mention[1];
                        this.mentionRange = { start: (element.selectionStart ?? 0) - mention[0].length, end: element.selectionStart ?? 0 };
                        this.mentionOpen = true;
                        this.mentionIndex = 0;

                        return;
                    }

                    this.mentionOpen = false;
                },

                syncSelection(event) {
                    this.selectionStart = event.target.selectionStart ?? 0;
                    this.selectionEnd = event.target.selectionEnd ?? 0;
                },

                get selectionLength() {
                    return Math.max(0, this.selectionEnd - this.selectionStart);
                },

                dismissSuggestions() {
                    this.mentionOpen = false;
                    this.slashOpen = false;
                },

                // The composer blurs when a suggestion is clicked; the click is
                // allowed to land first, then the panels close.
                onComposerBlur() {
                    setTimeout(() => this.dismissSuggestions(), 120);
                },

                moveSuggestion(direction) {
                    const list = this.slashOpen ? this.slashMatches() : this.mentionMatches();

                    if (list.length === 0) {
                        return;
                    }

                    if (this.slashOpen) {
                        this.slashIndex = (this.slashIndex + direction + list.length) % list.length;
                    } else {
                        this.mentionIndex = (this.mentionIndex + direction + list.length) % list.length;
                    }
                },

                acceptSuggestion() {
                    if (this.slashOpen) {
                        const command = this.slashMatches()[this.slashIndex];

                        if (command) {
                            this.runSlashCommand(command);
                        }

                        return;
                    }

                    if (this.mentionOpen) {
                        const item = this.mentionMatches()[this.mentionIndex];

                        if (item) {
                            this.applyMention(item);
                        }
                    }
                },

                // Enter accepts an open suggestion or sends; Shift+Enter keeps
                // its newline. The default is only suppressed in the two cases
                // where the key is consumed, so the browser still inserts the
                // line break when the user asked for one.
                onComposerEnter(event) {
                    if (this.mentionOpen || this.slashOpen) {
                        event.preventDefault();
                        this.acceptSuggestion();

                        return;
                    }

                    if (event.shiftKey || event.isComposing) {
                        return;
                    }

                    event.preventDefault();
                    this.send();
                },

                // Replace the partial "@..." token with the chosen item.
                applyMention(item) {
                    const range = this.mentionRange ?? { start: this.draft.length, end: this.draft.length };
                    const token = `@${item.id}`;

                    this.draft = this.draft.slice(0, range.start) + token + ' ' + this.draft.slice(range.end);
                    this.mentionOpen = false;

                    this.$nextTick(() => {
                        const input = this.$refs.input;

                        if (input) {
                            const caret = range.start + token.length + 1;
                            input.focus();
                            input.setSelectionRange(caret, caret);
                        }
                    });
                },

                runSlashCommand(command) {
                    // Strip the typed "/command" before acting on it.
                    const range = this.slashRange;

                    if (range) {
                        this.draft = this.draft.slice(0, range.start) + this.draft.slice(range.end);
                    }

                    this.slashOpen = false;

                    // /art is both an assistant mode and the board, since a
                    // sketch is how vector and image work usually starts.
                    if (command.id === 'art' || command.id === 'board') {
                        this.mode = 'art';
                        this.openBoard();

                        return;
                    }

                    const modeIds = this.modes.map((option) => option.id);

                    if (modeIds.includes(command.id)) {
                        this.mode = command.id;

                        return;
                    }

                    switch (command.id) {
                        case 'new':
                        case 'clear':
                            this.messages = [];
                            this.activeConversationId = null;
                            break;

                        case 'image':
                            this.mode = 'image';
                            break;

                        case 'deep':
                        case 'fast':
                        case 'super':
                            this.setDepth(command.id);
                            break;

                        case 'enhance':
                            this.enhancePrompt();
                            break;

                        case 'export':
                            this.exportConversation('md');
                            break;

                        case 'workspace':
                            window.location.href = this.routes.workspace;
                            break;

                        case 'studio':
                            window.location.href = this.routes.studio;
                            break;

                        case 'help':
                            this.draft = 'What can you do? List your modes, the sub-agents you can delegate to, and the tools you have.';
                            break;

                        default:
                            break;
                    }

                    this.$nextTick(() => this.$refs.input?.focus());
                },

                // ---- Draw and scratch board ------------------------------------
                boardPalette() {
                    return ['#0d0d0d', '#ef4444', '#f59e0b', '#10a37f', '#3b82f6', '#8b5cf6', '#ec4899', '#ffffff'];
                },

                openBoard() {
                    this.board.open = true;
                    this.board.error = null;
                    this.board.resultUrl = null;

                    // Painted only on the first open so closing and reopening
                    // the board never throws away the sketch.
                    if (!this.board.initialised) {
                        this.$nextTick(() => {
                            const canvas = this.$refs.boardCanvas;

                            if (canvas) {
                                canvas.getContext('2d').fillStyle = '#ffffff';
                                canvas.getContext('2d').fillRect(0, 0, canvas.width, canvas.height);
                                this.board.initialised = true;
                            }
                        });
                    }
                },

                closeBoard() {
                    this.board.open = false;
                },

                // Pointer position in canvas pixels, not CSS pixels, so the
                // stroke lands where the cursor is at any zoom level.
                boardPoint(event) {
                    const canvas = this.$refs.boardCanvas;
                    const rect = canvas.getBoundingClientRect();

                    return {
                        x: (event.clientX - rect.left) * (canvas.width / rect.width),
                        y: (event.clientY - rect.top) * (canvas.height / rect.height),
                    };
                },

                boardContext() {
                    const ctx = this.$refs.boardCanvas.getContext('2d');

                    ctx.lineCap = 'round';
                    ctx.lineJoin = 'round';
                    ctx.lineWidth = this.board.size;
                    ctx.strokeStyle = this.board.colour;
                    // The eraser punches through to transparency, which is why
                    // every export is flattened onto white first.
                    ctx.globalCompositeOperation = this.board.tool === 'eraser'
                        ? 'destination-out'
                        : 'source-over';

                    return ctx;
                },

                boardPointerDown(event) {
                    const canvas = this.$refs.boardCanvas;

                    if (!canvas) {
                        return;
                    }

                    this.boardPush();

                    try {
                        canvas.setPointerCapture(event.pointerId);
                    } catch (error) {
                        // Not every browser exposes capture; drawing still works.
                    }

                    const point = this.boardPoint(event);

                    this.board.drawing = true;
                    this.board.lastX = point.x;
                    this.board.lastY = point.y;
                    this.board.startX = point.x;
                    this.board.startY = point.y;

                    const ctx = this.boardContext();

                    // A shape is drawn onto a scratch layer that is composited
                    // over the canvas, so dragging shows only an outline and the
                    // result is committed on release. Painting straight onto the
                    // canvas would leave a half-drawn rectangle if the gesture was
                    // abandoned part way.
                    if (this.board.isShape) {
                        this.board.preview = document.createElement('canvas');
                        this.board.preview.width = canvas.width;
                        this.board.preview.height = canvas.height;

                        const overlay = this.$refs.boardOverlay;

                        if (overlay) {
                            overlay.width = canvas.width;
                            overlay.height = canvas.height;
                        }

                        return;
                    }

                    ctx.beginPath();
                    ctx.arc(point.x, point.y, ctx.lineWidth / 2, 0, Math.PI * 2);
                    ctx.fillStyle = this.board.colour;
                    ctx.fill();
                },

                boardPointerMove(event) {
                    if (!this.board.drawing) {
                        return;
                    }

                    const point = this.boardPoint(event);

                    if (this.board.isShape) {
                        this.boardDrawPreview(point);
                        return;
                    }

                    const ctx = this.boardContext();

                    ctx.beginPath();
                    ctx.moveTo(this.board.lastX, this.board.lastY);
                    ctx.lineTo(point.x, point.y);
                    ctx.stroke();

                    this.board.lastX = point.x;
                    this.board.lastY = point.y;
                },

                /**
                 * Render the in-progress shape onto the scratch layer.
                 */
                boardDrawPreview(point) {
                    const preview = this.board.preview;

                    if (!preview) {
                        return;
                    }

                    const ctx = preview.getContext('2d');
                    ctx.clearRect(0, 0, preview.width, preview.height);

                    this.boardShape(ctx, this.board.startX, this.board.startY, point.x, point.y);

                    const overlay = this.$refs.boardOverlay;

                    if (overlay?.getContext) {
                        const visible = overlay.getContext('2d');
                        visible.clearRect(0, 0, overlay.width, overlay.height);
                        visible.drawImage(preview, 0, 0);
                    }
                },

                /**
                 * Trace one shape, shared by the preview and the final commit so
                 * what was dragged is exactly what is kept.
                 */
                boardShape(ctx, x1, y1, x2, y2) {
                    const left = Math.min(x1, x2);
                    const top = Math.min(y1, y2);
                    const width = Math.abs(x2 - x1);
                    const height = Math.abs(y2 - y1);

                    ctx.beginPath();

                    switch (this.board.tool) {
                        case 'line':
                            ctx.moveTo(x1, y1);
                            ctx.lineTo(x2, y2);
                            break;
                        case 'rect':
                            ctx.rect(left, top, width, height);
                            break;
                        case 'ellipse':
                            ctx.ellipse(
                                left + width / 2,
                                top + height / 2,
                                width / 2,
                                height / 2,
                                0,
                                0,
                                Math.PI * 2,
                            );
                            break;
                        default:
                            return;
                    }

                    ctx.stroke();
                },

                boardPointerUp(event) {
                    if (!this.board.drawing) {
                        return;
                    }

                    this.board.drawing = false;

                    if (this.board.isShape && event) {
                        const point = this.boardPoint(event);
                        const ctx = this.boardContext();

                        // The shape is only committed now, so a cancelled drag
                        // leaves nothing behind on the canvas.
                        this.boardShape(ctx, this.board.startX, this.board.startY, point.x, point.y);

                        this.board.preview = null;

                        const overlay = this.$refs.boardOverlay;

                        if (overlay?.getContext) {
                            const visible = overlay.getContext('2d');
                            visible.clearRect(0, 0, overlay.width, overlay.height);
                        }
                    }

                    this.board.resultUrl = null;
                },

                boardPush() {
                    const canvas = this.$refs.boardCanvas;

                    if (!canvas) {
                        return;
                    }

                    this.board.history.push(canvas.toDataURL('image/png'));

                    // A shape is one history entry, so the cap is raised to keep
                    // a reasonable number of brush strokes alongside them.
                    if (this.board.history.length > 24) {
                        this.board.history.shift();
                    }
                },

                boardUndo() {
                    const url = this.board.history.pop();
                    const canvas = this.$refs.boardCanvas;

                    if (!url || !canvas) {
                        return;
                    }

                    const image = new Image();
                    image.onload = () => {
                        const ctx = canvas.getContext('2d');
                        ctx.globalCompositeOperation = 'source-over';
                        ctx.clearRect(0, 0, canvas.width, canvas.height);
                        ctx.drawImage(image, 0, 0);
                    };
                    image.src = url;
                },

                boardClear() {
                    this.boardPush();

                    const canvas = this.$refs.boardCanvas;

                    if (!canvas) {
                        return;
                    }

                    const ctx = canvas.getContext('2d');
                    ctx.globalCompositeOperation = 'source-over';
                    ctx.fillStyle = '#ffffff';
                    ctx.fillRect(0, 0, canvas.width, canvas.height);
                    this.board.resultUrl = null;
                    this.board.error = null;
                },

                // The canvas carries a transparent background for the eraser, so
                // every export is composited over white first — otherwise a PNG
                // with holes in it comes back with a black background.
                async boardBlob() {
                    const canvas = this.$refs.boardCanvas;
                    const flat = document.createElement('canvas');

                    flat.width = canvas.width;
                    flat.height = canvas.height;

                    const ctx = flat.getContext('2d');
                    ctx.fillStyle = '#ffffff';
                    ctx.fillRect(0, 0, flat.width, flat.height);
                    ctx.drawImage(canvas, 0, 0);

                    return new Promise((resolve) => flat.toBlob(resolve, 'image/png'));
                },

                async attachBoard() {
                    const blob = await this.boardBlob();

                    this.fileError = null;
                    this.handleFiles([new File([blob], 'sketch.png', { type: 'image/png' })]);
                    this.closeBoard();
                },

                async saveBoardToWorkspace() {
                    this.board.busy = true;
                    this.board.error = null;

                    try {
                        const blob = await this.boardBlob();
                        const form = new FormData();

                        form.append('files[]', new File([blob], 'sketch.png', { type: 'image/png' }));
                        form.append('directory', 'sketches');

                        const response = await fetch(this.routes.boardUpload, {
                            method: 'POST',
                            headers: this.headers(),
                            body: form,
                        });

                        if (!response.ok) {
                            const payload = await response.json().catch(() => null);
                            throw new Error(payload?.message ?? 'Could not save the sketch.');
                        }

                        // The file tree and the @-mention list both read this.
                        this.loadMentions();
                    } catch (error) {
                        this.board.error = error?.message || 'Could not save the sketch.';
                    } finally {
                        this.board.busy = false;
                    }
                },

                async downloadBoard() {
                    const blob = await this.boardBlob();
                    const url = URL.createObjectURL(blob);
                    const link = document.createElement('a');

                    link.href = url;
                    link.download = 'whale-sketch.png';
                    document.body.appendChild(link);
                    link.click();
                    link.remove();
                    setTimeout(() => URL.revokeObjectURL(url), 1000);
                },

                // Sketch in, styled image out: the drawing becomes the source
                // image for an edit request with the chosen style applied.
                async generateFromBoard() {
                    const prompt = this.board.prompt.trim();

                    if (!prompt || this.board.busy) {
                        return;
                    }

                    this.board.busy = true;
                    this.board.error = null;
                    this.board.resultUrl = null;

                    try {
                        const blob = await this.boardBlob();
                        const form = new FormData();

                        form.append('image', new File([blob], 'sketch.png', { type: 'image/png' }));
                        form.append('prompt', prompt);
                        form.append('style', this.imageStyle);
                        form.append('size', 'landscape');

                        const selection = this.selection();

                        if (selection.provider) {
                            form.append('provider', selection.provider);
                            form.append('model', selection.model ?? '');
                        }

                        const response = await fetch(this.routes.imageEdit, {
                            method: 'POST',
                            headers: this.headers(),
                            body: form,
                        });

                        const payload = await response.json().catch(() => null);

                        if (!response.ok) {
                            throw new Error(payload?.message ?? 'Could not generate an image from the sketch.');
                        }

                        this.board.resultUrl = payload?.url ?? null;

                        if (!this.board.resultUrl) {
                            throw new Error('The image provider returned no image.');
                        }
                    } catch (error) {
                        this.board.error = error?.message || 'Could not generate an image from the sketch.';
                    } finally {
                        this.board.busy = false;
                    }
                },

                // The sub-agent names actually mentioned in a message. Only
                // names the server knows about are sent, so a stray "@" word
                // never turns into a validation error.
                mentionedAgents(text) {
                    const known = this.mentionCatalog.map((agent) => agent.id);

                    return known.filter((name) => text.includes(`@${name}`));
                },

                // Wrap (or prefix) the current selection with markdown syntax.
                wrapSelection(prefix, suffix = prefix) {
                    const input = this.$refs.input;

                    if (!input) {
                        return;
                    }

                    const start = input.selectionStart ?? 0;
                    const end = input.selectionEnd ?? 0;
                    const selected = this.draft.slice(start, end);
                    const wrapped = `${prefix}${selected}${suffix}`;

                    this.draft = this.draft.slice(0, start) + wrapped + this.draft.slice(end);

                    this.$nextTick(() => {
                        input.focus();
                        input.setSelectionRange(start + prefix.length, start + prefix.length + selected.length);
                        this.syncSelection({ target: input });
                    });
                },

                selection() {
                    return {
                        provider: this.providerName ?? undefined,
                        model: this.modelName ?? undefined,
                    };
                },

                selectModel(provider, model) {
                    this.providerName = provider;
                    this.modelName = model;

                    try {
                        localStorage.setItem(MODEL_STORAGE_KEY, JSON.stringify({ provider, model }));
                    } catch (error) {
                        // A browser with storage disabled simply keeps the
                        // selection for this page view only.
                    }
                },

                exportConversation(format = 'txt') {
                    // A persisted thread exports through the server so it can be
                    // rendered as markdown, HTML or JSON; an unsaved one falls
                    // back to a client-side text download.
                    if (this.activeConversationId) {
                        const url = new URL(`${this.routes.history}/${this.activeConversationId}/export`, window.location.origin);
                        url.searchParams.set('format', format);
                        window.location.href = url.toString();

                        return;
                    }

                    const lines = this.messages
                        .filter((msg) => msg.content && (!msg.kind || msg.kind === 'text'))
                        .map((msg) => `${msg.role === 'user' ? 'You' : 'Whale'}: ${msg.content}`);

                    const file = new Blob([lines.join('\n\n')], { type: 'text/plain;charset=utf-8' });
                    const url = URL.createObjectURL(file);
                    const link = document.createElement('a');

                    link.href = url;
                    link.download = `whale-conversation.${format === 'md' ? 'md' : 'txt'}`;
                    document.body.appendChild(link);
                    link.click();
                    link.remove();
                    setTimeout(() => URL.revokeObjectURL(url), 1000);
                },
                // ---- Sharing a conversation --------------------------------
                shareActiveUrl() {
                    return this.shareUrl && this.shareUrlFor === this.activeConversationId
                        ? this.shareUrl
                        : null;
                },
                async shareConversation() {
                    if (!this.activeConversationId) {
                        return;
                    }

                    try {
                        const response = await fetch(`${this.routes.history}/${this.activeConversationId}/share`, {
                            method: 'POST',
                            headers: this.headers(),
                        });

                        const payload = await response.json().catch(() => null);

                        if (!response.ok) {
                            throw new Error(payload?.message || 'Could not share this conversation.');
                        }

                        this.shareUrl = payload.url;
                        this.shareUrlFor = this.activeConversationId;

                        // Copy when the browser allows it; the menu still
                        // shows the link when the clipboard is blocked.
                        await this.writeClipboard(payload.url);
                    } catch (error) {
                        this.error = error.message;
                    }
                },
                async stopSharing() {
                    if (!this.activeConversationId) {
                        return;
                    }

                    try {
                        const response = await fetch(`${this.routes.history}/${this.activeConversationId}/share`, {
                            method: 'DELETE',
                            headers: this.headers(),
                        });

                        if (!response.ok) {
                            throw new Error('Sharing could not be turned off.');
                        }
                    } catch (error) {
                        // The link is still live, so the share UI has to stay up
                        // as well — clearing it here would have claimed the chat
                        // was private when it was not.
                        this.showNotice(error.message ?? 'Sharing could not be turned off.');

                        return;
                    }

                    this.shareUrl = null;
                    this.shareUrlFor = null;
                },


                // ---- Sidebar: projects & history --------------------------------
                headers() {
                    return {
                        'Accept': 'application/json',
                        'X-Whale-Workspace': window.whaleWorkspaceId(),
                    };
                },

                async loadHistory() {
                    try {
                        const response = await fetch(this.routes.history, { headers: this.headers() });

                        if (!response.ok) {
                            return;
                        }

                        const payload = await response.json();

                        this.projects = payload?.projects ?? [];
                        this.conversations = payload?.conversations ?? [];
                        this.archived = payload?.archived ?? [];
                    } catch (error) {
                        // The sidebar simply stays empty; chat still works.
                    }
                },

                async createProject() {
                    const name = this.projectDraft.trim();

                    if (!name) {
                        return;
                    }

                    const response = await fetch(`${this.routes.history}/projects`, {
                        method: 'POST',
                        headers: { ...this.headers(), 'Content-Type': 'application/json' },
                        body: JSON.stringify({ name, workspace: window.whaleWorkspaceId() }),
                    });

                    if (response.ok) {
                        this.projectDraft = '';
                        this.projectDialogOpen = false;
                        this.loadHistory();
                    }
                },

                // The sidebar's shape is three separate preferences, and each is restored
                // independently so one stale value cannot take the others with
                // it.
                applyStoredSidebar() {
                    // Number(null) is 0 and Number('') is 0 as well, so an absent
                    // key would pass the isFinite check and clamp the 260px
                    // default straight down to the 200px minimum. Only a value
                    // that was genuinely written counts as a preference.
                    const storedWidth = this.read(SIDEBAR_WIDTH_STORAGE_KEY);

                    if (storedWidth !== null && String(storedWidth).trim() !== '') {
                        const width = Number(storedWidth);

                        if (Number.isFinite(width)) {
                            this.sidebarWidth = this.clampSidebarWidth(width);
                        }
                    }

                    this.sidebarCollapsed = this.read(SIDEBAR_COLLAPSED_STORAGE_KEY) === '1';
                    this.historyLayout = this.read(HISTORY_LAYOUT_STORAGE_KEY) === 'grid' ? 'grid' : 'list';
                },

                // Clamped in one place: the stored value, the drag and the reset
                // button all pass through here, so the sidebar can never end up
                // too narrow to read or wide enough to swallow the transcript.
                clampSidebarWidth(value) {
                    const width = Number(value) || SIDEBAR_MIN_WIDTH;

                    return Math.min(SIDEBAR_MAX_WIDTH, Math.max(SIDEBAR_MIN_WIDTH, Math.round(width)));
                },

                setSidebarWidth(value) {
                    this.sidebarWidth = this.clampSidebarWidth(value);
                    this.store(SIDEBAR_WIDTH_STORAGE_KEY, String(this.sidebarWidth));
                },

                toggleSidebar() {
                    // Below the breakpoint the sidebar is an overlay, so the
                    // toggle has to dismiss it rather than collapse it — there
                    // is no rail to collapse into on a phone.
                    if (window.matchMedia('(max-width: 1023px)').matches) {
                        this.mobileSidebar = false;

                        return;
                    }

                    this.sidebarCollapsed = !this.sidebarCollapsed;
                    this.store(SIDEBAR_COLLAPSED_STORAGE_KEY, this.sidebarCollapsed ? '1' : '0');
                },

                startSidebarResize(event) {
                    this.resizingSidebar = true;

                    // Capture on the handle but move on the window: a drag that
                    // outruns the 4px handle has to keep resizing.
                    event.target.setPointerCapture?.(event.pointerId);

                    const move = (moveEvent) => {
                        this.setSidebarWidth(moveEvent.clientX);
                    };

                    const stop = () => {
                        this.resizingSidebar = false;

                        window.removeEventListener('pointermove', move);
                        window.removeEventListener('pointerup', stop);
                        window.removeEventListener('pointercancel', stop);
                    };

                    window.addEventListener('pointermove', move);
                    window.addEventListener('pointerup', stop);
                    window.addEventListener('pointercancel', stop);
                },

                resetSidebarWidth() {
                    this.setSidebarWidth(SIDEBAR_DEFAULT_WIDTH);
                },

                setHistoryLayout(layout) {
                    this.historyLayout = layout === 'grid' ? 'grid' : 'list';
                    this.store(HISTORY_LAYOUT_STORAGE_KEY, this.historyLayout);
                },

                toggleHistoryLayout() {
                    this.setHistoryLayout(this.historyLayout === 'grid' ? 'list' : 'grid');
                },

                // ---- Reading preferences ---------------------------------------
                // Each preference is clamped to the same bounds the CSS honours,
                // so a hand-edited localStorage value cannot produce a layout
                // the stylesheet has no rule for.
                applyPreferences() {
                    const scale = Number(this.read(READING_SCALE_STORAGE_KEY));
                    const density = Number(this.read(DENSITY_STORAGE_KEY));

                    this.readingScale = Number.isFinite(scale) && scale > 0
                        ? Math.min(1.25, Math.max(0.9, scale))
                        : 1;

                    // Density is a gap in rem, stored as a plain number of rem.
                    this.transcriptDensity = Number.isFinite(density) && density > 0
                        ? Math.min(3, Math.max(0.5, density))
                        : 1.5;

                    this.reduceMotion = this.read(REDUCE_MOTION_STORAGE_KEY) === '1';
                    this.paintPreferences();
                },

                // The single place the DOM is touched. Writing an invalid value
                // here is a no-op on the stylesheet, so this is also where a
                // stale store gets corrected rather than left to fail silently.
                paintPreferences() {
                    const root = document.documentElement;

                    root.style.setProperty('--reading-scale', String(this.readingScale));
                    root.style.setProperty('--density-gap', `${this.transcriptDensity}rem`);
                    root.classList.toggle('reduce-motion', this.reduceMotion);
                },

                setReadingScale(value) {
                    this.readingScale = Math.min(1.25, Math.max(0.9, Number(value) || 1));
                    this.store(READING_SCALE_STORAGE_KEY, String(this.readingScale));
                    this.paintPreferences();
                },

                setTranscriptDensity(value) {
                    this.transcriptDensity = Math.min(3, Math.max(0.5, Number(value) || 1.5));
                    this.store(DENSITY_STORAGE_KEY, String(this.transcriptDensity));
                    this.paintPreferences();
                },

                setReduceMotion(value) {
                    this.reduceMotion = Boolean(value);
                    this.store(REDUCE_MOTION_STORAGE_KEY, this.reduceMotion ? '1' : '0');
                    this.paintPreferences();
                },

                resetPreferences() {
                    this.setReadingScale(1);
                    this.setTranscriptDensity(1.5);
                    this.setReduceMotion(false);
                },

                // One place decides what "grid" means, because it is applied to
                // three different containers: the date groups, the chats nested
                // in a project, and the archive — plus the rows inside them,
                // which have to agree or a collapsed sidebar renders grid cards
                // inside a single-column list. Grid mode is only ever hidden
                // while collapsed, where there is no room for two columns.
                isGridHistory() {
                    return this.historyLayout === 'grid' && !this.sidebarCollapsed;
                },

                historyLayoutClass() {
                    return this.isGridHistory() ? 'grid grid-cols-2 gap-1.5' : 'space-y-0.5';
                },

                // Search covered only the unfiled chats, so anything filed under
                // a project was invisible to it — which read as "search is
                // broken" rather than "search is scoped".
                matchesSearch(chat) {
                    const query = this.searchQuery.trim().toLowerCase();

                    if (!query) {
                        return true;
                    }

                    return [chat.title, chat.preview]
                        .filter(Boolean)
                        .some((field) => String(field).toLowerCase().includes(query));
                },

                visibleConversations() {
                    return this.conversations.filter((chat) => this.matchesSearch(chat));
                },

                visibleProjectConversations(project) {
                    return project.conversations.filter((chat) => this.matchesSearch(chat));
                },

                visibleProjects() {
                    return this.projects.filter((project) => this.visibleProjectConversations(project).length > 0);
                },

                hasSearchResults() {
                    if (!this.searchQuery.trim()) {
                        return this.conversations.length > 0 || this.projects.length > 0;
                    }

                    return this.visibleConversations().length > 0 || this.visibleProjects().length > 0;
                },

                // Date groups need a real timestamp to compare, which is why the
                // history payload carries an ISO `updated_at` alongside the
                // human one. Boundaries are start-of-day, so a thread moved at
                // 23:50 and one moved at 00:10 land in different groups.
                conversationGroups() {
                    // Everything is bucketed against the start of today rather
                    // than against "24 hours ago", so a thread touched at 23:50
                    // last night and one touched at 00:10 this morning land in
                    // different groups instead of both being "today".
                    const startOfToday = new Date();
                    startOfToday.setHours(0, 0, 0, 0);

                    const dayMs = 24 * 60 * 60 * 1000;
                    const midnight = startOfToday.getTime();

                    const buckets = [
                        { label: 'Today', max: 1 },
                        { label: 'Yesterday', max: 2 },
                        { label: 'Previous 7 days', max: 7 },
                        { label: 'Previous 30 days', max: 30 },
                    ];

                    const groups = new Map(buckets.map((bucket) => [bucket.label, []]));
                    groups.set('Older', []);

                    for (const chat of this.visibleConversations()) {
                        const updated = Date.parse(chat.updated_at ?? '');

                        // An unparseable timestamp is not worth hiding a thread
                        // over, so it falls back to the oldest group.
                        const days = Number.isNaN(updated)
                            ? Infinity
                            : Math.floor((midnight - updated) / dayMs) + 1;

                        const match = buckets.find((bucket) => days <= bucket.max);

                        groups.get(match?.label ?? 'Older').push(chat);
                    }

                    return [...groups.entries()]
                        .filter(([, chats]) => chats.length > 0)
                        .map(([label, chats]) => ({ label, chats }));
                },

                archivedGroups() {
                    const dayMs = 24 * 60 * 60 * 1000;
                    const now = Date.now();

                    const today = [];
                    const earlier = [];

                    for (const chat of this.archived) {
                        const updated = Date.parse(chat.updated_at ?? '');
                        const age = Number.isNaN(updated) ? Infinity : now - updated;

                        (age < dayMs ? today : earlier).push(chat);
                    }

                    return [
                        { label: 'Archived today', chats: today },
                        { label: 'Archived earlier', chats: earlier },
                    ].filter((group) => group.chats.length > 0);
                },

                // ---- Command palette -------------------------------------------------
                // Two lists: the commands you can invoke, and the threads you
                // can jump to. One query filters both and the thread results are
                // ranked above commands, because "the conversation about the
                // migration" is almost always what Ctrl+K was pressed for.
                openPalette() {
                    // Recorded before the state flips: once the dialog has the
                    // caret, "what opened this" is no longer document.activeElement
                    // and closing would strand the user in a hidden input.
                    this.returnFocusTo = document.activeElement;

                    this.palette = { open: true, query: '', index: 0 };
                    this.$nextTick(() => this.$refs.paletteInput?.focus());
                },

                closePalette() {
                    this.palette.open = false;
                },

                togglePalette() {
                    if (this.palette.open) {
                        this.closePalette();
                    } else {
                        this.openPalette();
                    }
                },

                movePalette(delta) {
                    const items = this.paletteItems();

                    if (items.length === 0) {
                        return;
                    }

                    this.palette.index = (this.palette.index + delta + items.length) % items.length;
                },

                runPalette() {
                    const item = this.paletteItems()[this.palette.index] ?? this.paletteItems()[0];

                    if (item) {
                        this.runPaletteItem(item);
                    }
                },

                async runPaletteItem(item) {
                    this.closePalette();

                    // `run` is a function on the item, which is why the list can
                    // be built as data. Async so a thread can load without the
                    // palette waiting on it.
                    await item.run();
                },

                paletteItems() {
                    const query = this.palette.query.trim().toLowerCase();

                    const threads = this.conversations
                        .filter((chat) => !query
                            || (chat.title ?? '').toLowerCase().includes(query)
                            || (chat.preview ?? '').toLowerCase().includes(query))
                        .slice(0, 30)
                        .map((chat) => ({
                            id: `chat:${chat.id}`,
                            kind: 'chat',
                            label: chat.title || 'Untitled',
                            hint: chat.updated_human ?? '',
                            run: () => this.openConversation(chat),
                        }));

                    const matches = (value) => !query || String(value).toLowerCase().includes(query);

                    const commands = this.commandList()
                        .filter((command) => matches(command.label) || matches(command.keywords))
                        .map((command) => ({
                            id: `command:${command.id}`,
                            kind: 'command',
                            label: command.label,
                            hint: command.hint,
                            run: command.run,
                        }));

                    return [...threads, ...commands];
                },

                commandList() {
                    const list = [
                        {
                            id: 'chat.new',
                            label: 'Start a new chat',
                            hint: 'Ctrl+K',
                            keywords: 'new fresh conversation',
                            run: () => { window.location.href = this.routes.history; },
                        },
                        {
                            id: 'chat.settings',
                            label: 'Open settings',
                            hint: 'Providers and appearance',
                            keywords: 'provider model api key appearance font text size density motion',
                            run: () => { this.settingsOpen = true; },
                        },
                        {
                            id: 'chat.sidebar',
                            label: this.sidebarCollapsed ? 'Expand sidebar' : 'Collapse sidebar',
                            hint: '',
                            keywords: 'rail hide show drawer',
                            run: () => { this.toggleSidebar(); },
                        },
                        {
                            id: 'chat.layout',
                            label: this.historyLayout === 'grid' ? 'History as list' : 'History as grid',
                            hint: '',
                            keywords: 'layout grid list view tiles sidebar',
                            run: () => { this.toggleHistoryLayout(); },
                        },
                        {
                            id: 'chat.sidebar-width',
                            label: 'Reset sidebar width',
                            hint: `${this.sidebarWidth}px`,
                            keywords: 'resize reset width',
                            run: () => { this.resetSidebarWidth(); },
                        },
                        {
                            id: 'chat.preferences',
                            label: 'Reset appearance preferences',
                            hint: 'Text size, spacing, motion',
                            keywords: 'reset font size density motion preferences',
                            run: () => { this.resetPreferences(); },
                        },
                        {
                            id: 'chat.workspace',
                            label: 'Open workspace',
                            hint: 'Code',
                            keywords: 'ide files editor project',
                            run: () => { window.location.href = this.routes.workspace; },
                        },
                        {
                            id: 'chat.studio',
                            label: 'Open studio',
                            hint: 'Media',
                            keywords: 'image audio video generate',
                            run: () => { window.location.href = this.routes.studio; },
                        },
                    ];

                    return list;
                },

                // A destructive action is two steps: describe it, then confirm it. Keeping
                // them apart means the wording can name what will actually
                // happen rather than just asking "are you sure?".
                askConfirm({ title, body, confirmLabel = 'Confirm', danger = false, onConfirm }) {
                    this.confirmDialog = { title, body, confirmLabel, danger, onConfirm, busy: false };
                },

                // The dialog closes whatever the outcome, so a caller never has
                // to remember to dismiss it and a failure cannot strand it open.
                async runConfirm() {
                    const dialog = this.confirmDialog;

                    if (!dialog || dialog.busy) {
                        return;
                    }

                    dialog.busy = true;

                    try {
                        await dialog.onConfirm();
                    } catch (error) {
                        // The dialog is already closing, so without this the
                        // action's failure would leave no trace at all.
                        this.lastError = error?.message ?? String(error);
                        this.showNotice(this.lastError);
                    } finally {
                        this.confirmDialog = null;
                    }
                },

                // Renaming used to be window.prompt, which cannot be styled and
                // blocks the event loop. The row swaps to an input instead.
                startRename(conversation) {
                    this.menuFor = null;
                    this.renamingId = conversation.id;
                    this.renameDraft = conversation.title;

                    // Queried by id rather than $refs: the field is created by an
                    // x-if inside an x-for, so it only exists after this tick.
                    this.$nextTick(() => {
                        const input = document.getElementById(`rename-${conversation.id}`);

                        input?.focus();
                        input?.select();
                    });
                },

                cancelRename() {
                    this.renamingId = null;
                    this.renameDraft = '';
                },

                async commitRename(conversation) {
                    const title = this.renameDraft.trim();

                    if (!title || title === conversation.title) {
                        this.cancelRename();

                        return;
                    }

                    this.cancelRename();

                    await this.updateConversation(conversation.id, { title });
                },

                renameProject(project) {
                    // The same inline field the chats use, rather than a second
                    // mechanism: window.prompt cannot be styled and blocked the
                    // whole thread while it was open.
                    this.menuFor = null;
                    this.renamingProjectId = project.id;
                    this.projectRenameDraft = project.name;

                    this.$nextTick(() => {
                        const input = document.getElementById(`rename-project-${project.id}`);

                        input?.focus();
                        input?.select();
                    });
                },

                cancelProjectRename() {
                    this.renamingProjectId = null;
                    this.projectRenameDraft = '';
                },

                async commitProjectRename(project) {
                    const name = this.projectRenameDraft.trim();

                    if (!name || name === project.name) {
                        this.cancelProjectRename();

                        return;
                    }

                    this.cancelProjectRename();

                    const response = await fetch(`${this.routes.history}/projects/${project.id}`, {
                        method: 'PATCH',
                        headers: { ...this.headers(), 'Content-Type': 'application/json' },
                        body: JSON.stringify({ name, workspace: window.whaleWorkspaceId() }),
                    });

                    // The rename field is already closed by this point, so a
                    // failure would otherwise leave the old name showing with
                    // nothing to say the new one was refused.
                    if (!response.ok) {
                        this.showNotice('That project could not be renamed.');
                    }

                    await this.loadHistory();
                },

                async deleteProject(project) {
                    this.askConfirm({
                        title: `Delete "${project.name}"?`,
                        body: 'The chats inside it are kept and move back to your history.',
                        confirmLabel: 'Delete project',
                        danger: true,
                        onConfirm: async () => {
                            const response = await fetch(`${this.routes.history}/projects/${project.id}`, {
                                method: 'DELETE',
                                headers: this.headers(),
                            });

                            if (!response.ok) {
                                throw new Error('That project could not be deleted.');
                            }

                            await this.loadHistory();
                        },
                    });
                },

                async togglePinned(conversation) {
                    await this.updateConversation(conversation.id, { pinned: !conversation.pinned });
                },

                async deleteConversation(conversation) {
                    this.menuFor = null;

                    this.askConfirm({
                        title: `Delete "${conversation.title}"?`,
                        body: 'This removes the chat and every message in it. It cannot be undone.',
                        confirmLabel: 'Delete chat',
                        danger: true,
                        onConfirm: async () => {
                            const response = await fetch(`${this.routes.history}/${conversation.id}`, {
                                method: 'DELETE',
                                headers: this.headers(),
                            });

                            // Throwing hands the failure to the dialog's own
                            // handler, which closes it and shows the notice —
                            // without this the chat simply stayed listed with
                            // no explanation of why it was still there.
                            if (!response.ok) {
                                throw new Error('That chat could not be deleted.');
                            }

                            if (this.activeConversationId === conversation.id) {
                                this.activeConversationId = null;
                                this.messages = [];
                            }

                            await this.loadHistory();
                        },
                    });
                },

                async archiveConversation(conversation) {
                    this.menuFor = null;

                    await this.updateConversation(conversation.id, { archived: true });

                    if (this.activeConversationId === conversation.id) {
                        this.activeConversationId = null;
                        this.messages = [];
                    }

                    await this.loadHistory();
                },

                async restoreConversation(conversation) {
                    await this.updateConversation(conversation.id, { archived: false });

                    await this.loadHistory();
                },

                async updateConversation(id, patch) {
                    const response = await fetch(`${this.routes.history}/${id}`, {
                        method: 'PATCH',
                        headers: { ...this.headers(), 'Content-Type': 'application/json' },
                        body: JSON.stringify({ ...patch, workspace: window.whaleWorkspaceId() }),
                    });

                    // Renaming, pinning, archiving and filing into a project all
                    // arrive through here, and the history is reloaded either way
                    // so the list reflects what the server actually holds. The
                    // notice is what tells the two apart: without it a rejected
                    // change reloaded the old value and the click looked ignored.
                    if (!response.ok) {
                        this.showNotice('That change could not be saved.');
                    }

                    await this.loadHistory();
                },

                // Dropping a chat onto a project files it there.
                async dropConversation(projectId) {
                    if (!this.draggedConversationId) {
                        return;
                    }

                    await this.updateConversation(this.draggedConversationId, { project_id: projectId });
                    this.draggedConversationId = null;
                },

                async openConversation(conversation) {
                    try {
                        const response = await fetch(`${this.routes.history}/${conversation.id}/messages`, {
                            headers: this.headers(),
                        });

                        if (!response.ok) {
                            // Previously this returned silently, so a chat that
                            // could not be opened looked identical to one that had
                            // not been clicked. 403 specifically means the thread
                            // belongs to another workspace.
                            const message = response.status === 403
                                ? 'That chat belongs to a different workspace.'
                                : 'Could not open that conversation.';

                            this.lastError = message;
                            this.showNotice(message);

                            return;
                        }

                        const payload = await response.json();

                        this.activeConversationId = conversation.id;
                        this.mode = conversation.mode ?? 'chat';
                        this.messages = (payload?.messages ?? []).map((message) => this.hydrateMessage(message));

                        this.$nextTick(() => this.maybeScrollToBottom());
                    } catch (error) {
                        this.lastError = 'Could not open that conversation.';
                        this.showNotice(this.lastError);
                    }
                },

                // Persisted messages carry only the fields the database knows;
                // the UI's transient flags are filled in here.
                hydrateMessage(message) {
                    return {
                        id: message.id ?? this.nextMessageId(),
                        role: message.role,
                        kind: message.kind ?? 'text',
                        content: message.content ?? '',
                        attachments: message.meta?.attachments ?? [],
                        edited: message.meta?.edited === true,
                        editing: false,
                        time: message.meta?.time ?? '',
                        streaming: false,
                        stopped: false,
                        error: null,
                        reasoning: [],
                        reasoningOpen: false,
                        reaction: message.meta?.reaction ?? null,
                    };
                },

                // Persisting is best-effort: a failed write must never interrupt
                // a stream the user is watching.
                async persistMessage(message) {
                    if (!this.activeConversationId) {
                        return;
                    }

                    try {
                        await fetch(`${this.routes.history}/${this.activeConversationId}/messages`, {
                            method: 'POST',
                            headers: { ...this.headers(), 'Content-Type': 'application/json' },
                            body: JSON.stringify({
                                role: message.role,
                                kind: message.kind ?? 'text',
                                content: message.content ?? '',
                                meta: {
                                    edited: message.edited === true,
                                    time: message.time,
                                    attachments: (message.attachments ?? []).map((file) => ({
                                        name: file.name,
                                        kind: file.kind,
                                    })),
                                },
                                workspace: window.whaleWorkspaceId(),
                            }),
                        });
                    } catch (error) {
                        // Ignored by design.
                    }
                },

                // ---- Message reactions ------------------------------------------
                react(message, reaction) {
                    message.reaction = message.reaction === reaction ? null : reaction;
                },

                // ---- Prompt enhancement -----------------------------------------
                async enhancePrompt(style = 'clearer') {
                    const text = this.draft.trim();

                    if (!text || this.enhancing) {
                        return;
                    }

                    this.enhancing = true;
                    this.enhancedDraft = null;

                    try {
                        const response = await fetch(this.routes.enhance, {
                            method: 'POST',
                            headers: { ...this.headers(), 'Content-Type': 'application/json' },
                            body: JSON.stringify({
                                prompt: text,
                                style,
                                history: this.historyPayload(undefined, 6),
                                ...this.selection(),
                            }),
                        });

                        const payload = await response.json().catch(() => null);

                        if (!response.ok) {
                            throw new Error(this.messageForFailure(response.status, payload));
                        }

                        // Nothing to offer if the rewrite is identical.
                        if (payload?.enhanced && payload.enhanced !== text) {
                            this.enhancedOriginal = text;
                            this.enhancedDraft = payload.enhanced;
                        }
                    } catch (error) {
                        this.lastError = error.message;
                        this.showNotice(this.lastError);
                    } finally {
                        this.enhancing = false;
                    }
                },

                acceptEnhanced() {
                    if (this.enhancedDraft) {
                        this.draft = this.enhancedDraft;
                    }

                    this.dismissEnhanced();
                    this.$nextTick(() => this.$refs.input?.focus());
                },

                dismissEnhanced() {
                    this.enhancedDraft = null;
                    this.enhancedOriginal = null;
                },

                // Modes, depths and mentionable agents all come from the server,
                // so the pickers can never offer something the API would reject.
                async loadCapabilities() {
                    try {
                        const response = await fetch(this.routes.capabilities, {
                            headers: { 'Accept': 'application/json' },
                        });

                        if (!response.ok) {
                            return;
                        }

                        const payload = await response.json();

                        if (Array.isArray(payload?.modes) && payload.modes.length) {
                            this.modes = payload.modes;
                        }

                        if (Array.isArray(payload?.depths) && payload.depths.length) {
                            this.depths = payload.depths;
                        }

                        if (Array.isArray(payload?.efforts) && payload.efforts.length) {
                            this.efforts = payload.efforts;
                        }

                        if (Array.isArray(payload?.lengths) && payload.lengths.length) {
                            this.lengths = payload.lengths;

                            // The server is the source of truth for the tier
                            // list, so a stored length has to be re-resolved
                            // against it rather than trusted.
                            this.syncLengthIndex();
                        }

                        this.mentionCatalog = payload?.mentions ?? [];

                        if (Array.isArray(payload?.styles) && payload.styles.length) {
                            this.imageStyles = payload.styles;
                        }
                    } catch (error) {
                        // The built-in defaults stay in place.
                    }
                },

                // The workspace files offered by the @-mention picker.
                async loadMentions() {
                    try {
                        const response = await fetch(this.routes.mentions, { headers: this.headers() });

                        if (!response.ok) {
                            return;
                        }

                        const payload = await response.json();

                        this.mentionFiles = payload?.files ?? [];
                    } catch (error) {
                        this.mentionFiles = [];
                    }
                },

                applyStoredDepth() {
                    const depth = this.read(DEPTH_STORAGE_KEY);

                    if (depth && this.depths.some((option) => option.id === depth)) {
                        this.depth = depth;
                    }

                    const effort = this.read(EFFORT_STORAGE_KEY);

                    if (effort && this.efforts.some((option) => option.id === effort)) {
                        this.thinking = effort;
                    }

                    const length = this.read(LENGTH_STORAGE_KEY);

                    if (length && this.lengths.some((option) => option.id === length)) {
                        this.length = length;
                    }

                    // The slider position is derived from the id, never stored
                    // separately, so the two can never drift apart.
                    this.syncLengthIndex();
                },

                formatBytes(bytes) {
                    const value = Number(bytes) || 0;

                    if (value < 1024 * 1024) {
                        return `${Math.round(value / 1024)} KB`;
                    }

                    if (value < 1024 * 1024 * 1024) {
                        return `${(value / 1024 / 1024).toFixed(0)} MB`;
                    }

                    return `${(value / 1024 / 1024 / 1024).toFixed(2)} GB`;
                },

                async loadLocalModels() {
                    try {
                        // The settings panel is the only place runtime health is
                        // shown, so the probe is paid here and nowhere else.
                        const url = new URL(this.routes.localModels, window.location.origin);
                        url.searchParams.set('health', '1');

                        const response = await fetch(url, {
                            headers: { 'Accept': 'application/json' },
                        });

                        if (!response.ok) {
                            return;
                        }

                        const payload = await response.json();

                        this.localModels = payload?.models ?? [];
                        this.modelsPath = payload?.path ?? '';
                        this.unreadableModels = payload?.unreadable ?? [];
                        this.runtimeHealth = payload?.runtimes ?? {};
                    } catch (error) {
                        // Detection is read-only; failing to list files must never
                        // break the chat itself.
                    }
                },

                /**
                 * The endpoints declared in WHALE_CUSTOM_PROVIDERS, and the models
                 * kept for each. The API key is deliberately never part of this
                 * payload; only whether one was set travels.
                 */
                async loadCustomProviders() {
                    try {
                        const response = await fetch(this.routes.customProviders, {
                            headers: { 'Accept': 'application/json' },
                        });

                        if (!response.ok) {
                            return;
                        }

                        const payload = await response.json();

                        this.customProviders = payload?.providers ?? [];
                    } catch (error) {
                        // Only the settings section depends on this, so an empty
                        // section is a better outcome than an error.
                    }
                },

                customProviderModelsUrl(provider) {
                    return `${this.routes.customProviders}/${encodeURIComponent(provider)}/models`;
                },

                customProviderSelectionFor(provider) {
                    return this.customProviderSelection[provider] ?? [];
                },

                toggleCustomModel(provider, modelId) {
                    const chosen = this.customProviderSelectionFor(provider);

                    this.customProviderSelection[provider] = chosen.includes(modelId)
                        ? chosen.filter((id) => id !== modelId)
                        : [...chosen, modelId];
                },

                async discoverCustomModels(provider) {
                    this.customProviderBusy[provider] = 'discover';

                    try {
                        const response = await fetch(this.customProviderModelsUrl(provider), {
                            headers: { 'Accept': 'application/json' },
                        });

                        const payload = await response.json().catch(() => null);

                        if (!response.ok || !payload) {
                            throw new Error(`Could not reach ${provider}.`);
                        }

                        this.customProviderDiscovered[provider] = payload.models ?? [];

                        // A reachable=false result means the endpoint could not be
                        // asked, which is different from an endpoint that serves
                        // nothing, so it is surfaced rather than shown as empty.
                        if (payload.reachable === false) {
                            this.showNotice(payload.message ?? `Could not reach ${provider}.`);
                        }
                    } catch (error) {
                        this.showNotice(error.message);
                    } finally {
                        this.customProviderBusy[provider] = null;
                    }
                },

                async importCustomModels(provider) {
                    const chosen = this.customProviderSelectionFor(provider);

                    if (chosen.length === 0) {
                        return;
                    }

                    this.customProviderBusy[provider] = 'import';

                    try {
                        const response = await fetch(
                            `${this.routes.customProviders}/${encodeURIComponent(provider)}/import`,
                            {
                                method: 'POST',
                                headers: { ...this.headers(), 'Content-Type': 'application/json' },
                                body: JSON.stringify({ models: chosen }),
                            },
                        );

                        const payload = await response.json().catch(() => null);

                        if (!response.ok || !payload) {
                            throw new Error(`Could not import from ${provider}.`);
                        }

                        this.customProviderSelection[provider] = [];

                        await this.loadCustomProviders();

                        this.showNotice(`Kept ${payload.imported.length} model(s) from ${provider}.`);
                    } catch (error) {
                        this.showNotice(error.message);
                    } finally {
                        this.customProviderBusy[provider] = null;
                    }
                },

                async syncCustomModels(provider) {
                    this.customProviderBusy[provider] = 'sync';

                    try {
                        const response = await fetch(
                            `${this.routes.customProviders}/${encodeURIComponent(provider)}/sync`,
                            {
                                method: 'POST',
                                headers: { ...this.headers(), 'Accept': 'application/json' },
                            },
                        );

                        const payload = await response.json().catch(() => null);

                        if (!response.ok || !payload) {
                            throw new Error(`Could not sync ${provider}.`);
                        }

                        await this.loadCustomProviders();

                        this.showNotice(payload.unreachable
                            ? `Could not reach ${provider}, so its models were left alone.`
                            : `Synced ${provider}: ${payload.added.length} added, ${payload.removed.length} removed.`);
                    } catch (error) {
                        this.showNotice(error.message);
                    } finally {
                        this.customProviderBusy[provider] = null;
                    }
                },

                async loadCatalog() {
                    try {
                        const response = await fetch(this.routes.models, {
                            headers: { 'Accept': 'application/json' },
                        });

                        if (!response.ok) {
                            return;
                        }

                        const payload = await response.json();

                        this.providers = payload?.providers ?? [];
                        this.applyStoredSelection(payload?.default ?? {});
                    } catch (error) {
                        // The picker keeps showing "Select a model"; chat
                        // itself still works against the configured default.
                    }
                },

                applyStoredSelection(fallback) {
                    const isUsable = (provider, model) => this.providers.some((entry) =>
                        entry.name === provider
                        && entry.configured
                        && entry.models.some((candidate) => candidate.id === model && candidate.configured));

                    let stored = null;

                    try {
                        stored = JSON.parse(localStorage.getItem(MODEL_STORAGE_KEY) ?? 'null');
                    } catch (error) {
                        stored = null;
                    }

                    const provider = stored?.provider ?? fallback?.provider ?? null;
                    const model = stored?.model ?? fallback?.model ?? null;

                    if (isUsable(provider, model)) {
                        this.providerName = provider;
                        this.modelName = model;

                        return;
                    }

                    this.providerName = fallback?.provider ?? null;
                    this.modelName = fallback?.model ?? null;
                },

                /**
                 * What the composer shows while it is empty.
                 *
                 * The starter prompts rotate here rather than sitting as
                 * buttons under the greeting, so the hint is where typing
                 * starts and there is nothing extra to click past. Anything
                 * typed takes precedence: the rotation never runs while a
                 * draft exists, and it never restores the plain hint over a
                 * prompt the user has actually started.
                 */
                get placeholder() {
                    if (this.mode === 'image') {
                        return 'Describe the image to generate...';
                    }

                    if (this.draft.trim()) {
                        return '';
                    }

                    return STARTER_PROMPTS[this.starterPromptIndex] ?? '';
                },

                /**
                 * Advance the hint, unless there is a draft to leave alone.
                 */
                rotateStarterPrompt() {
                    if (this.draft.trim()) {
                        return;
                    }

                    this.starterPromptIndex =
                        (this.starterPromptIndex + 1) % STARTER_PROMPTS.length;
                },

                startStarterPrompts() {
                    this.stopStarterPrompts();

                    // Plain text swapping, so nothing here animates and
                    // reduced-motion preferences are satisfied by default.
                    this.starterPromptTimer = setInterval(
                        () => this.rotateStarterPrompt(),
                        STARTER_PROMPT_INTERVAL
                    );
                },

                stopStarterPrompts() {
                    if (this.starterPromptTimer) {
                        clearInterval(this.starterPromptTimer);
                        this.starterPromptTimer = null;
                    }
                },

                get announcement() {
                    switch (this.status) {
                        case 'thinking':
                            return 'Thinking…';
                        case 'responding':
                            return 'Responding…';
                        case 'stopped':
                            return 'Stopped.';
                        case 'error':
                            return this.lastError ?? 'The assistant request failed.';
                        default:
                            return '';
                    }
                },

                // The timer is cleared before every new notice and on dismissal,
                // so two failures in a row cannot leave a stale countdown that
                // hides the newer message early.
                showNotice(message) {
                    clearTimeout(this.noticeTimer);
                    this.notice = message;
                    this.noticeTimer = setTimeout(() => this.dismissNotice(), 8000);
                },

                dismissNotice() {
                    clearTimeout(this.noticeTimer);
                    this.noticeTimer = null;
                    this.notice = null;
                },

                // --- Modal focus handling ---------------------------------
                // aria-modal tells assistive technology that the rest of the page
                // is inert, but it does nothing for a real keyboard: without this,
                // Tab keeps walking through the background while the dialog is
                // open. Alpine's Focus plugin is not loaded here, so the trap is
                // written out rather than pulling in another CDN script.
                trapping: null,
                returnFocusTo: null,

                trapFocusIn(dialog) {
                    if (!dialog || this.trapping === dialog) {
                        return;
                    }

                    // Held across the release below: a caller that already
                    // recorded the trigger (openPalette does, because it knows
                    // the caret it is taking focus from) wins over whatever
                    // happens to be focused by the time the effect runs.
                    const trigger = this.returnFocusTo;

                    this.releaseFocus();
                    this.trapping = dialog;

                    // Captured before focus moves, so closing restores the button
                    // or caret that opened the dialog rather than dropping the
                    // user at the top of the document.
                    this.returnFocusTo = trigger ?? document.activeElement;

                    const initial = dialog.querySelector(
                        '[autofocus], input:not([type="hidden"]), textarea, select, button, a[href], [tabindex]:not([tabindex="-1"])',
                    );

                    this.$nextTick(() => initial?.focus({ preventScroll: true }));
                },

                releaseFocus() {
                    if (!this.trapping) {
                        return;
                    }

                    this.trapping = null;

                    const target = this.returnFocusTo;
                    this.returnFocusTo = null;

                    if (target && document.contains(target)) {
                        target.focus({ preventScroll: true });
                    }
                },

                // Tab is handled at the edges only: anything in the middle of the
                // dialog is left to the browser, which already orders it correctly.
                keepFocusInside(event) {
                    if (event.key !== 'Tab' || !this.trapping) {
                        return;
                    }

                    const scope = this.trapping;
                    const focusable = [...scope.querySelectorAll(
                        'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])',
                    )].filter((element) => element.offsetParent !== null || element === document.activeElement);

                    if (!focusable.length) {
                        event.preventDefault();

                        return;
                    }

                    const first = focusable[0];
                    const last = focusable[focusable.length - 1];
                    const active = document.activeElement;
                    const outside = !scope.contains(active);

                    if (event.shiftKey && (active === first || outside)) {
                        event.preventDefault();
                        last.focus();
                    } else if (!event.shiftKey && (active === last || outside)) {
                        event.preventDefault();
                        first.focus();
                    }
                },

                init() {
                    this.loadCatalog();
                    this.loadCapabilities();
                    this.loadHistory();
                    this.loadMentions();
                    this.applyStoredDepth();
                    this.applyStoredSidebar();
                    this.applyPreferences();

                    // Live capture hands its transcript over on the window, so
                    // this side never has to know the panel exists.
                    //
                    // The old handler is dropped first because init() can run
                    // twice (x-init plus Alpine's own lifecycle hook), and a
                    // second registration would fire the transcript in twice.
                    if (this.onLiveTranscript) {
                        window.removeEventListener('live-transcript', this.onLiveTranscript);
                    }

                    this.onLiveTranscript = (event) => this.applyLiveTranscript(event);
                    window.addEventListener('live-transcript', this.onLiveTranscript);

                    this.startStarterPrompts();

                    this.$nextTick(() => this.maybeScrollToBottom());
                },

                /**
                 * Take a transcript from live capture and put it in the draft.
                 *
                 * Focus lands in the composer because that is where the user
                 * wants to be afterwards: the words are in the box, and the
                 * next move is reading them, editing them, or typing on.
                 */
                applyLiveTranscript(event) {
                    const text = (event.detail?.text ?? '').trim();

                    if (!text) {
                        return;
                    }

                    // Appended, never replaced: a half-written draft has to
                    // survive a detour through the microphone.
                    this.draft = this.draft.trim()
                        ? `${this.draft.trim()} ${text}`
                        : text;

                    this.$nextTick(() => {
                        const input = this.$refs.input;

                        if (!input) {
                            return;
                        }

                        this.autoGrow({ target: input });
                        input.focus();

                        // Caret to the end, where the next thing gets typed.
                        const end = input.value.length;
                        input.setSelectionRange(end, end);
                    });
                },

                // Escape closes the sidebar on a phone, matching the drawer
                // behaviour rather than the desktop rail's.
                onSidebarKeydown(event) {
                    if (event.key === 'Escape' && this.mobileSidebar) {
                        this.mobileSidebar = false;
                    }
                },

                destroy() {
                    this.abortController?.abort();
                    this.stopStarterPrompts();
                    window.removeEventListener('live-transcript', this.onLiveTranscript);
                },

                onScroll() {
                    const scroller = this.$refs.scroller;

                    if (!scroller) {
                        return;
                    }

                    this.isPinnedToBottom =
                        scroller.scrollHeight - scroller.scrollTop - scroller.clientHeight < 48;
                },

                maybeScrollToBottom() {
                    const scroller = this.$refs.scroller;

                    if (!scroller || !this.isPinnedToBottom) {
                        return;
                    }

                    scroller.scrollTop = scroller.scrollHeight;
                },

                autoGrow(event) {
                    event.target.style.height = 'auto';
                    event.target.style.height = `${Math.min(event.target.scrollHeight, 200)}px`;
                },

                stamp() {
                    return new Date().toLocaleTimeString([], {
                        hour: '2-digit',
                        minute: '2-digit',
                    });
                },

                nextMessageId() {
                    idCounter += 1;

                    return `${Date.now()}-${idCounter}`;
                },

                // 422, 429 and 503 arrive as ordinary JSON before any stream begins.
                messageForFailure(status, payload) {
                    if ((status === 422 || status === 503) && payload?.message) {
                        return payload.message;
                    }

                    if (status === 429) {
                        return 'You are sending messages too quickly. Please wait and try again.';
                    }

                    return 'Could not reach the assistant. Please try again.';
                },

                // Prior turns replayed to the server so the agent keeps context.
                // Media cards carry no text and are skipped.
                historyPayload(endIndex = this.messages.length, limit = 40) {
                    return this.messages
                        .slice(0, endIndex)
                        .filter((msg) => (msg.role === 'user' || msg.role === 'assistant')
                            && typeof msg.content === 'string'
                            && msg.content.trim() !== ''
                            && (!msg.kind || msg.kind === 'text'))
                        .slice(-limit)
                        .map((msg) => ({ role: msg.role, content: msg.content }));
                },

                // Assistant text is rendered client-side and sanitized before it
                // reaches x-html; user text stays plain.
                renderMarkdown(text) {
                    const source = typeof text === 'string' ? text : '';

                    if (!source) {
                        return '';
                    }

                    if (typeof marked === 'undefined' || typeof DOMPurify === 'undefined') {
                        return `<p>${escapeText(source).replace(/\n/g, '<br>')}</p>`;
                    }

                    try {
                        return DOMPurify.sanitize(marked.parse(source, {
                            breaks: true,
                            gfm: true,
                            renderer: buildRenderer(),
                        }));
                    } catch {
                        return `<p>${escapeText(source)}</p>`;
                    }
                },

                // x-html content is never compiled by Alpine, so the copy and
                // sandbox buttons inside code blocks are handled by delegation.
                handleCodeBlockClick(event) {
                    const button = event.target.closest('[data-code-action]');

                    if (!button || !this.$el.contains(button)) {
                        return;
                    }

                    const block = button.closest('.code-block');
                    const code = block?.querySelector('code')?.textContent ?? '';
                    const lang = block?.dataset.lang || button.dataset.codeLang || 'html';

                    if (button.dataset.codeAction === 'copy') {
                        this.writeClipboard(code).then((ok) => {
                            this.flashButton(button, ok ? 'Copied' : 'Failed');
                        });

                        return;
                    }

                    if (button.dataset.codeAction === 'sandbox') {
                        this.openSandbox(code, lang);
                    }
                },

                async writeClipboard(value) {
                    const text = value ?? '';

                    if (navigator.clipboard?.writeText) {
                        try {
                            await navigator.clipboard.writeText(text);

                            return true;
                        } catch {
                            // Fall through to the legacy path.
                        }
                    }

                    try {
                        const helper = document.createElement('textarea');
                        helper.value = text;
                        helper.setAttribute('readonly', '');
                        helper.style.position = 'fixed';
                        helper.style.opacity = '0';
                        document.body.appendChild(helper);
                        helper.select();
                        const copied = document.execCommand('copy');
                        helper.remove();

                        return copied;
                    } catch {
                        return false;
                    }
                },

                flashButton(button, label) {
                    if (!button) {
                        return;
                    }

                    const original = button.innerHTML;
                    button.textContent = label;
                    setTimeout(() => {
                        button.innerHTML = original;
                    }, 1400);
                },

                async copyText(text, event) {
                    const ok = await this.writeClipboard(text);
                    const target = event?.currentTarget;

                    // Delegated handlers report the container as currentTarget.
                    if (target instanceof HTMLElement && target.tagName === 'BUTTON') {
                        this.flashButton(target, ok ? 'Copied' : 'Failed');
                    }
                },

                // Reads the Vercel AI data stream protocol frame by frame. Frames are
                // split on a blank line, an optional "data: " prefix is stripped, and
                // anything after the last boundary is kept for the next chunk.
                async readStream(body, onPart) {
                    const reader = body.getReader();
                    const decoder = new TextDecoder();

                    let buffer = '';

                    while (true) {
                        const { done, value } = await reader.read();

                        if (done) {
                            break;
                        }

                        // Multi-byte characters can straddle chunk boundaries.
                        buffer += decoder.decode(value, { stream: true });

                        let boundary = buffer.indexOf('\n\n');

                        while (boundary !== -1) {
                            const frame = buffer.slice(0, boundary);

                            buffer = buffer.slice(boundary + 2);

                            if (frame.startsWith('data:')) {
                                const payload = frame.slice(5).trim();

                                if (payload === '[DONE]') {
                                    return;
                                }

                                let part = null;

                                try {
                                    part = JSON.parse(payload);
                                } catch {
                                    part = null;
                                }

                                if (part) {
                                    onPart(part);
                                }
                            }

                            boundary = buffer.indexOf('\n\n');
                        }
                    }
                },

                // Aborting closes the socket. It does not cancel the provider call.
                stop() {
                    if (!this.isStreaming) {
                        return;
                    }

                    this.abortController?.abort();
                },

                // Re-hydrates the composer attachments when a prompt is edited or
                // regenerated. The original File handles are kept on the message.
                restorePending(attachments) {
                    this.pendingFiles = attachments
                        .filter((file) => file?.file)
                        .map((file) => ({ file: file.file, name: file.name, kind: file.kind, url: file.url }));
                },

                startEdit(msg) {
                    if (this.isStreaming || !msg?.content) {
                        return;
                    }

                    this.messages.forEach((item) => {
                        item.editing = false;
                    });

                    msg.editing = true;
                    this.editingId = msg.id;
                    this.editDraft = msg.content;

                    this.$nextTick(() => {
                        const textarea = this.$el.querySelector('.prompt-editor textarea');

                        if (textarea) {
                            textarea.focus();
                            textarea.selectionStart = textarea.selectionEnd = textarea.value.length;
                            this.autoGrow({ target: textarea });
                        }
                    });
                },

                cancelEdit() {
                    this.messages.forEach((item) => {
                        item.editing = false;
                    });

                    this.editingId = null;
                    this.editDraft = '';
                },

                saveEdit(msg) {
                    const text = this.editDraft.trim();

                    if (this.isStreaming || (!text && !(msg.attachments ?? []).length)) {
                        return;
                    }

                    const index = this.messages.findIndex((item) => item.id === msg.id);

                    if (index === -1) {
                        return;
                    }

                    // Everything after the edited prompt is discarded, exactly like
                    // ChatGPT's "edit and resend" behaviour.
                    this.messages = this.messages.slice(0, index);
                    this.editingId = null;
                    this.editDraft = '';
                    this.restorePending(msg.attachments ?? []);
                    this.draft = text;
                    this.send({ edited: true });
                },

                regenerate(msg) {
                    if (this.isStreaming) {
                        return;
                    }

                    const index = this.messages.findIndex((item) => item.id === msg.id);

                    if (index === -1) {
                        return;
                    }

                    let promptIndex = index - 1;

                    while (promptIndex >= 0 && this.messages[promptIndex].role !== 'user') {
                        promptIndex -= 1;
                    }

                    if (promptIndex < 0) {
                        return;
                    }

                    const prompt = this.messages[promptIndex];

                    this.messages = this.messages.slice(0, promptIndex);
                    this.restorePending(prompt.attachments ?? []);
                    this.draft = prompt.content;
                    this.send();
                },

                isLastAssistant(msg) {
                    const last = [...this.messages].reverse().find((item) => item.role === 'assistant');

                    return last ? last.id === msg.id : false;
                },

                // ---- Attachments -------------------------------------------------
                pickFiles(accept = 'image/*,video/*', forOcr = false) {
                    const input = this.$refs.fileInput;

                    if (!input) {
                        return;
                    }

                    // Reset on every pick so a cancelled dialog cannot leave the
                    // composer stuck in OCR mode.
                    this.ocrPending = forOcr;
                    input.accept = accept;
                    input.click();
                },

                startOcrPick() {
                    this.pickFiles('image/*', true);
                },

                // Mirrors the server-side rules: four files, 8 MB images, 25 MB videos.
                // Pasting is the main way long prompts and screenshots arrive,
                // so it gets first refusal: a clipboard image becomes an
                // attachment, and rich pasted HTML is flattened to plain text so
                // no markup or styling leaks into the message.
                handlePaste(event) {
                    const clipboard = event.clipboardData;

                    if (!clipboard) {
                        return;
                    }

                    const items = Array.from(clipboard.items ?? []);
                    const imageItem = items.find((item) => item.kind === 'file' && item.type.startsWith('image/'));

                    if (imageItem) {
                        const file = imageItem.getAsFile();

                        if (file) {
                            event.preventDefault();
                            this.fileError = null;
                            this.handleFiles([file]);

                            return;
                        }
                    }

                    const html = clipboard.getData('text/html');

                    if (html) {
                        event.preventDefault();
                        this.insertAtCaret(clipboard.getData('text/plain'));
                    }
                },

                // Insert text at the caret, replacing the current selection.
                insertAtCaret(text) {
                    const value = String(text ?? '');

                    if (value === '') {
                        return;
                    }

                    const start = this.selectionStart ?? this.draft.length;
                    const end = this.selectionEnd ?? this.draft.length;
                    const prefix = this.draft.slice(0, start);
                    const suffix = this.draft.slice(end);

                    this.draft = prefix + value + suffix;

                    this.$nextTick(() => {
                        const input = this.$refs.input;

                        if (!input) {
                            return;
                        }

                        const caret = start + value.length;

                        input.focus();
                        input.setSelectionRange(caret, caret);
                        this.autoGrow({ target: input });
                        this.syncSelection({ target: input });
                    });
                },

                handleFiles(fileList) {
                    const incoming = Array.from(fileList ?? []);
                    const ocrRequest = this.ocrPending;
                    this.ocrPending = false;

                    if (incoming.length === 0) {
                        return;
                    }

                    if (ocrRequest) {
                        const image = incoming.find((file) => file.type.startsWith('image/'));

                        if (!image) {
                            this.fileError = 'OCR needs an image file.';

                            return;
                        }

                        this.fileError = null;
                        this.runOcr(image);

                        return;
                    }

                    this.fileError = null;

                    for (const file of incoming) {
                        const kind = file.type.startsWith('image/')
                            ? 'image'
                            : (file.type.startsWith('video/') ? 'video' : null);

                        if (!kind) {
                            this.fileError = `"${file.name}" is not a supported image or video.`;

                            continue;
                        }

                        const limit = kind === 'video' ? 25 : 8;

                        if (file.size > limit * 1024 * 1024) {
                            this.fileError = `"${file.name}" is larger than ${limit} MB.`;

                            continue;
                        }

                        if (this.pendingFiles.length >= 4) {
                            this.fileError = 'You can attach up to 4 files per message.';

                            break;
                        }

                        this.pendingFiles.push({
                            file,
                            name: file.name,
                            kind,
                            url: URL.createObjectURL(file),
                        });
                    }

                    this.$nextTick(() => this.maybeScrollToBottom());
                },

                removePendingFile(index) {
                    const [removed] = this.pendingFiles.splice(index, 1);

                    if (removed?.url) {
                        URL.revokeObjectURL(removed.url);
                    }
                },

                // ---- Image generation --------------------------------------------
                async generateImage(prompt, existing = null) {
                    const text = prompt.trim();

                    if (!text) {
                        return;
                    }

                    if (!existing) {
                        this.messages.push({
                            id: this.nextMessageId(),
                            role: 'user',
                            kind: 'text',
                            content: text,
                            attachments: [],
                            edited: false,
                            editing: false,
                            time: this.stamp(),
                            streaming: false,
                            stopped: false,
                            error: null,
                            reasoning: [],
                            reasoningOpen: false,
                        });
                    }

                    const card = existing ?? {
                        id: this.nextMessageId(),
                        role: 'assistant',
                        kind: 'image',
                        content: '',
                        imagePrompt: text,
                        imageState: 'loading',
                        imageUrl: null,
                        imageLoaded: false,
                        imageSize: 'landscape',
                        imageError: null,
                        time: this.stamp(),
                        streaming: false,
                        reasoning: [],
                        reasoningOpen: false,
                    };

                    if (existing) {
                        card.imageState = 'loading';
                        card.imageUrl = null;
                        card.imageLoaded = false;
                        card.imageError = null;
                    } else {
                        this.messages.push(card);
                    }

                    this.draft = '';
                    this.fileError = null;
                    this.isStreaming = true;
                    this.status = 'responding';
                    this.abortController = new AbortController();

                    this.$nextTick(() => {
                        if (this.$refs.input) {
                            this.$refs.input.style.height = 'auto';
                        }

                        this.maybeScrollToBottom();
                    });

                    try {
                        const response = await fetch(this.routes.image, {
                            method: 'POST',
                            headers: {
                                'Accept': 'application/json',
                                'Content-Type': 'application/json',
                            },
                            body: JSON.stringify({ prompt: text, size: card.imageSize ?? 'landscape', style: this.imageStyle, ...this.selection() }),
                            signal: this.abortController.signal,
                        });

                        const payload = await response.json().catch(() => null);

                        if (!response.ok) {
                            throw new Error(this.messageForFailure(response.status, payload));
                        }

                        card.imageUrl = payload?.url ?? null;
                        card.imageError = card.imageUrl ? null : 'The image provider returned no image.';
                        card.imageState = card.imageUrl ? 'ready' : 'error';
                    } catch (error) {
                        card.imageState = 'error';
                        card.imageError = error?.name === 'AbortError'
                            ? 'Image generation stopped.'
                            : (error?.message || 'Could not generate the image.');
                    } finally {
                        this.isStreaming = false;
                        this.abortController = null;
                        this.status = card.imageState === 'error' ? 'error' : 'idle';
                        this.$nextTick(() => this.maybeScrollToBottom());
                    }
                },

                retryImage(msg) {
                    if (this.isStreaming) {
                        return;
                    }

                    this.generateImage(msg.imagePrompt ?? '', msg);
                },

                // ---- OCR ----------------------------------------------------------
                async runOcr(file) {
                    if (!file) {
                        return;
                    }

                    this.messages.push({
                        id: this.nextMessageId(),
                        role: 'user',
                        kind: 'text',
                        content: 'Read the text in this image.',
                        attachments: [{ name: file.name, kind: 'image', url: URL.createObjectURL(file), file }],
                        edited: false,
                        editing: false,
                        time: this.stamp(),
                        streaming: false,
                        stopped: false,
                        error: null,
                        reasoning: [],
                        reasoningOpen: false,
                    });

                    const card = {
                        id: this.nextMessageId(),
                        role: 'assistant',
                        kind: 'ocr',
                        content: '',
                        ocrFile: file,
                        ocrState: 'loading',
                        ocrText: '',
                        ocrError: null,
                        time: this.stamp(),
                        streaming: false,
                        reasoning: [],
                        reasoningOpen: false,
                    };

                    this.messages.push(card);
                    this.$nextTick(() => this.maybeScrollToBottom());

                    await this.requestOcr(card, file);
                },

                async requestOcr(card, file) {
                    card.ocrState = 'loading';
                    card.ocrError = null;

                    try {
                        const body = new FormData();
                        body.append('image', file);

                        const selection = this.selection();

                        if (selection.provider) {
                            body.append('provider', selection.provider);
                            body.append('model', selection.model ?? '');
                        }

                        const response = await fetch(this.routes.ocr, {
                            method: 'POST',
                            headers: { 'Accept': 'application/json' },
                            body,
                        });

                        const payload = await response.json().catch(() => null);

                        if (!response.ok) {
                            throw new Error(this.messageForFailure(response.status, payload));
                        }

                        card.ocrText = payload?.text ?? '';
                        card.ocrState = 'ready';
                    } catch (error) {
                        card.ocrState = 'error';
                        card.ocrError = error?.message || 'Could not read the image.';
                    } finally {
                        this.$nextTick(() => this.maybeScrollToBottom());
                    }
                },

                retryOcr(msg) {
                    if (msg?.ocrFile) {
                        this.requestOcr(msg, msg.ocrFile);
                    }
                },

                // ---- Text to speech ----------------------------------------------
                async speak(msg) {
                    if (!msg?.content || msg.audioState === 'loading' || msg.audioUrl) {
                        return;
                    }

                    msg.audioState = 'loading';
                    msg.audioError = null;

                    try {
                        const response = await fetch(this.routes.audio, {
                            method: 'POST',
                            headers: {
                                'Accept': 'application/json',
                                'Content-Type': 'application/json',
                            },
                            body: JSON.stringify({ text: msg.content.slice(0, 4000), voice: 'female', ...this.selection() }),
                        });

                        const payload = await response.json().catch(() => null);

                        if (!response.ok) {
                            throw new Error(this.messageForFailure(response.status, payload));
                        }

                        if (!payload?.url) {
                            throw new Error('The audio provider returned no audio.');
                        }

                        msg.audioUrl = payload.url;
                        msg.audioState = 'ready';
                    } catch (error) {
                        msg.audioState = 'error';
                        msg.audioError = error?.message || 'Could not read this message aloud.';
                    }
                },

                // ---- Code sandbox -------------------------------------------------
                openSandbox(code, lang) {
                    this.sandbox = {
                        open: true,
                        lang: (lang || 'html').toLowerCase(),
                        code,
                        original: code,
                        dirty: false,
                        previewNote: '',
                    };

                    this.$nextTick(() => this.runSandbox());
                },

                closeSandbox() {
                    clearTimeout(sandboxTimer);
                    this.sandbox.open = false;
                },

                onSandboxEdit() {
                    this.sandbox.dirty = this.sandbox.code !== this.sandbox.original;
                    clearTimeout(sandboxTimer);
                    sandboxTimer = setTimeout(() => this.runSandbox(), 400);
                },

                insertSandboxTab(event) {
                    const textarea = event.target;
                    const start = textarea.selectionStart;
                    const end = textarea.selectionEnd;

                    textarea.value = `${textarea.value.slice(0, start)}    ${textarea.value.slice(end)}`;
                    textarea.selectionStart = textarea.selectionEnd = start + 4;
                    this.sandbox.code = textarea.value;
                    this.onSandboxEdit();
                },

                resetSandbox() {
                    this.sandbox.code = this.sandbox.original;
                    this.sandbox.dirty = false;
                    this.runSandbox();
                },

                runSandbox() {
                    const frame = this.$refs.sandboxFrame;

                    if (!frame) {
                        return;
                    }

                    this.sandbox.previewNote = '';
                    frame.srcdoc = this.sandboxDocument();
                    this.sandbox.previewNote = this.sandbox.previewNote || 'Auto-refreshes as you type';
                },

                // Only scripts are enabled in the iframe sandbox: no same-origin,
                // no top-level navigation, no forms.
                sandboxDocument() {
                    const code = this.sandbox.code;
                    const lang = this.sandbox.lang;
                    const head = '<!doctype html><html><head><meta charset="utf-8">'
                        + '<meta name="viewport" content="width=device-width, initial-scale=1">';

                    if (lang === 'css') {
                        return `${head}<style>${code}</style></head><body>${SANDBOX_SAMPLE}</body></html>`;
                    }

                    if (lang === 'markdown' || lang === 'md') {
                        const body = typeof marked !== 'undefined' ? marked.parse(code) : escapeText(code);

                        return `${head}<body style="font:14px/1.6 system-ui,sans-serif;padding:16px">${body}</body></html>`;
                    }

                    if (['js', 'javascript', 'mjs', 'ts', 'typescript'].includes(lang)) {
                        const guarded = code.replace(/<\/script/gi, '<\\/script');
                        const lines = [
                            '<body style="margin:0">',
                            '<pre id="whale-sandbox-out" style="font:13px/1.5 ui-monospace,monospace;box-sizing:border-box;'
                                + 'margin:0;padding:12px;min-height:100vh;white-space:pre-wrap"></pre>',
                            '<script>',
                            'const out = document.getElementById("whale-sandbox-out");',
                            'const log = (...values) => {',
                            '    out.textContent += values.map((value) => (typeof value === "object" ? JSON.stringify(value) : String(value))).join(" ") + "\\n";',
                            '};',
                            'console.log = console.info = console.warn = console.error = log;',
                            'try {',
                            guarded,
                            '} catch (error) {',
                            '    log("Error: " + (error && error.message ? error.message : error));',
                            '}',
                            '<\\/script>',
                            '</body></html>',
                        ];

                        return head + lines.join('\n');
                    }

                    if (['php', 'blade', 'sql', 'bash', 'sh'].includes(lang)) {
                        this.sandbox.previewNote = 'No live preview for this language';

                        return `${head}<body style="font:14px/1.6 system-ui,sans-serif;padding:16px;color:#64748b">`
                            + `Live preview is not available for ${lang} snippets. Edit, copy or download the code instead.`
                            + '</body></html>';
                    }

                    return /<html[\s>]/i.test(code) ? code : `${head}<body>${code}</body></html>`;
                },

                copySandbox(event) {
                    this.copyText(this.sandbox.code, event);
                },

                downloadSandbox() {
                    const extensions = {
                        html: 'html', htm: 'html', xml: 'xml', svg: 'svg', css: 'css',
                        js: 'js', javascript: 'js', mjs: 'js', ts: 'ts', typescript: 'ts',
                        json: 'json', php: 'php', md: 'md', markdown: 'md', sql: 'sql',
                    };
                    const extension = extensions[this.sandbox.lang] || 'txt';
                    const url = URL.createObjectURL(new Blob([this.sandbox.code], { type: 'text/plain;charset=utf-8' }));
                    const link = document.createElement('a');

                    link.href = url;
                    link.download = `whale-snippet.${extension}`;
                    document.body.appendChild(link);
                    link.click();
                    link.remove();
                    setTimeout(() => URL.revokeObjectURL(url), 1000);
                },

                async send(options = {}) {
                    const text = this.draft.trim();
                    const hasFiles = this.pendingFiles.length > 0;

                    if ((!text && !hasFiles) || this.isStreaming) {
                        return;
                    }

                    // Image mode turns the composer into a generation prompt box.
                    if (this.mode === 'image') {
                        this.generateImage(text);

                        return;
                    }

                    // The history snapshot is taken before the new turn is appended.
                    const history = this.historyPayload();
                    const files = this.pendingFiles.slice();
                    this.pendingFiles = [];

                    this.messages.push({
                        id: this.nextMessageId(),
                        role: 'user',
                        kind: 'text',
                        content: text,
                        attachments: files.map((file) => ({
                            name: file.name,
                            kind: file.kind,
                            url: file.url,
                            file: file.file,
                        })),
                        edited: options.edited === true,
                        editing: false,
                        time: this.stamp(),
                        streaming: false,
                        stopped: false,
                        error: null,
                        reasoning: [],
                        reasoningOpen: false,
                    });

                    this.draft = '';
                    this.status = 'thinking';
                    this.isStreaming = true;
                    this.lastError = null;
                    this.abortController = new AbortController();

                    this.$nextTick(() => {
                        if (this.$refs.input) {
                            this.$refs.input.style.height = 'auto';
                        }
                    });

                    let message = null;
                    let settled = false;
                    let sawFinish = false;
                    let stepHadText = false;
                    let pendingSeparator = false;
                    let frame = null;

                    // Deliberately closure-local rather than Alpine state: local Ollama
                    // emits tokens far faster than the display refreshes, and appending
                    // each one to a reactive property re-renders the bubble per token.
                    let pendingText = '';
                    let pendingReasoning = new Map();

                    const assistant = () => {
                        if (!message) {
                            this.messages.push({
                                id: this.nextMessageId(),
                                role: 'assistant',
                                kind: 'text',
                                content: '',
                                attachments: [],
                                edited: false,
                                editing: false,
                                time: this.stamp(),
                                streaming: true,
                                stopped: false,
                                error: null,
                                reasoning: [],
                                reasoningOpen: false,
                                usage: null,
                            });

                            message = this.messages[this.messages.length - 1];
                        }

                        return message;
                    };

                    const flush = () => {
                        frame = null;

                        if (pendingText) {
                            assistant().content += pendingText;
                            pendingText = '';
                        }

                        if (pendingReasoning.size) {
                            for (const [id, delta] of pendingReasoning) {
                                const block = assistant().reasoning.find((item) => item.id === id);

                                if (block) {
                                    block.text += delta;
                                }
                            }

                            pendingReasoning.clear();
                        }

                        // Only after Alpine has written the DOM: scrollHeight is stale
                        // until then, and a scroll event fired against a stale height
                        // would clear the pin and strand the viewport mid-stream.
                        this.$nextTick(() => this.maybeScrollToBottom());
                    };

                    const schedule = () => {
                        if (frame === null) {
                            frame = requestAnimationFrame(flush);
                        }
                    };

                    const settle = () => {
                        if (settled) {
                            return;
                        }

                        settled = true;

                        if (frame !== null) {
                            cancelAnimationFrame(frame);
                            frame = null;

                            flush();
                        }

                        const target = assistant();

                        target.streaming = false;
                        target.reasoningOpen = false;

                        // The protocol drops the terminal parts after an error, so a
                        // stream that ends without a finish part was cut short.
                        if (!target.error && !target.stopped && !sawFinish) {
                            target.error = 'The assistant response ended unexpectedly.';
                        }

                        this.lastError = target.error;
                        this.isStreaming = false;
                        this.abortController = null;
                        this.status = target.stopped
                            ? 'stopped'
                            : (target.error ? 'error' : 'idle');

                        this.$nextTick(() => this.maybeScrollToBottom());
                    };

                    const applyPart = (part) => {
                        switch (part.type) {
                            case 'start':
                                assistant();
                                break;

                            case 'start-step':
                                // TextDelta::combine() drops empty steps and joins the
                                // rest with a blank line, so the separator is deferred
                                // until this step actually produces text.
                                if (stepHadText) {
                                    pendingSeparator = true;
                                }

                                stepHadText = false;
                                break;

                            case 'text-start':
                                this.status = 'responding';
                                break;

                            case 'text-delta':
                                this.status = 'responding';

                                if (pendingSeparator) {
                                    pendingText += '\n\n';
                                    pendingSeparator = false;
                                }

                                pendingText += part.delta;
                                stepHadText = true;
                                schedule();
                                break;

                            case 'reasoning-start': {
                                const target = assistant();

                                target.reasoning.push({ id: part.id, text: '', done: false });
                                target.reasoningOpen = true;
                                break;
                            }

                            case 'reasoning-delta': {
                                const target = assistant();
                                const block = target.reasoning.find((item) => item.id === part.id);

                                if (block) {
                                    pendingReasoning.set(
                                        part.id,
                                        (pendingReasoning.get(part.id) ?? '') + part.delta
                                    );
                                    schedule();
                                }

                                break;
                            }

                            case 'reasoning-end': {
                                const block = assistant().reasoning.find((item) => item.id === part.id);

                                if (block) {
                                    block.done = true;
                                }

                                break;
                            }

                            case 'finish':
                                sawFinish = true;
                                assistant().usage = part.messageMetadata?.usage ?? null;
                                break;

                            case 'error':
                                assistant().error = part.errorText || 'The assistant request failed.';
                                break;

                            default:
                                break;
                        }
                    };

                    let failureMessage = 'Could not reach the assistant. Please try again.';

                    const headers = { 'Accept': 'text/event-stream' };
                    let body;

                    // Attachments force multipart so the files travel with the prompt;
                    // history is flattened into PHP's nested array syntax.
                    if (files.length === 0) {
                        headers['Content-Type'] = 'application/json';
                        body = JSON.stringify({
                            message: text,
                            history,
                            mode: this.mode,
                            depth: this.depth,
                            thinking: this.thinking,
                            length: this.length,
                            web: this.web ? 1 : 0,
                            deep_search: this.deepSearch ? 1 : 0,
                            mentions: this.mentionedAgents(text),
                            workspace: window.whaleWorkspaceId(),
                            ...this.selection(),
                        });
                    } else {
                        body = new FormData();
                        body.append('message', text);
                        body.append('mode', this.mode);
                        body.append('depth', this.depth);
                        body.append('thinking', this.thinking);
                        body.append('length', this.length);
                        body.append('web', this.web ? '1' : '0');
                        body.append('deep_search', this.deepSearch ? '1' : '0');
                        body.append('workspace', window.whaleWorkspaceId());
                        this.mentionedAgents(text).forEach((name) => body.append('mentions[]', name));
                        history.forEach((entry, index) => {
                            body.append(`history[${index}][role]`, entry.role);
                            body.append(`history[${index}][content]`, entry.content);
                        });
                        files.forEach((file) => body.append('attachments[]', file.file));

                        const selection = this.selection();

                        if (selection.provider) {
                            body.append('provider', selection.provider);
                            body.append('model', selection.model ?? '');
                        }
                    }

                    try {
                        const response = await fetch(this.endpoint, {
                            method: 'POST',
                            headers,
                            body,
                            signal: this.abortController.signal,
                        });

                        const contentType = response.headers.get('content-type') ?? '';

                        if (!response.ok || !contentType.includes('text/event-stream') || !response.body) {
                            const payload = await response.json().catch(() => null);

                            failureMessage = this.messageForFailure(response.status, payload);

                            throw new Error('The assistant request failed.');
                        }

                        await this.readStream(response.body, applyPart);
                    } catch (error) {
                        if (error?.name === 'AbortError') {
                            assistant().stopped = true;
                        } else {
                            assistant().error = failureMessage;
                        }
                    } finally {
                        settle();
                    }
                },
            };
        }
    </script>
    @endpush
@endsection