{{--
    Live capture: microphone and camera.

    Speech is recorded with MediaRecorder and sent in short clips so words appear
    while the user is still talking. The committed transcript is kept separate
    from the in-flight one, because a chunk that fails must not destroy what has
    already been said.

    Every request is a plain fetch against the endpoints in routes/chat.php; the
    server decides which engine answers.
--}}
{{-- One Alpine scope wraps both the trigger and the panel, so the trigger can
     read and set the same state the panel renders from. --}}
<div x-data="liveCapture()">
<button
    type="button"
    x-show="!open"
    x-on:click="show()"
    x-cloak
    class="grid h-8 w-8 place-items-center rounded-full text-[#5d5d5d] transition hover:bg-black/[0.06] focus-visible:outline-2 focus-visible:outline-accent dark:text-[#b4b4b4] dark:hover:bg-white/[0.1]"
    aria-label="Open live capture"
>
    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <path stroke-linecap="round" stroke-linejoin="round"
              d="M12 18.75a6 6 0 0 0 6-6v-1.5m-6 7.5a6 6 0 0 1-6-6v-1.5m6 7.5v3.75m-3.75 0h7.5M12 15.75a3 3 0 0 1-3-3V5.25a3 3 0 0 1 6 0v7.5a3 3 0 0 1-3 3Z"/>
    </svg>
</button>

<div
    x-show="open"
    x-cloak
    @keydown.escape.window="close()"
    x-on:keydown.escape.stop="close()"
    class="fixed inset-0 z-50 flex items-end justify-center bg-black/50 p-4 backdrop-blur-sm sm:items-center"
    role="dialog"
    aria-modal="true"
    aria-label="Live capture"
    tabindex="-1"
    x-ref="panel"
>
    <div class="flex max-h-[90vh] w-full max-w-2xl flex-col overflow-hidden rounded-2xl bg-white shadow-2xl dark:bg-[#212121]">
        {{-- Header --}}
        <div class="flex items-center justify-between border border-black/[0.08] border-b px-5 py-4 dark:border-white/10 dark:border-b">
            <div>
                <h2 class="text-sm font-semibold text-[#0d0d0d] dark:text-[#ececec]">Talk &amp; look</h2>
                <p class="mt-0.5 text-xs text-black/50 dark:text-white/50" x-text="status"></p>
            </div>
            <button
                type="button"
                x-on:click="close()"
                class="rounded-full p-1.5 text-black/50 transition hover:bg-black/[0.08] hover:text-[#0d0d0d] dark:text-white/50 dark:hover:bg-white/10 dark:hover:text-[#ececec]"
                aria-label="Close"
            >
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>

        <div class="min-h-0 flex-1 overflow-y-auto px-5 py-4">
            {{-- Errors are shown rather than swallowed, so a dead microphone or
                 a missing engine never looks like the app is simply quiet. --}}
            <div
                x-show="error"
                x-text="error"
                class="mb-4 rounded-xl bg-red-50 px-4 py-3 text-sm text-red-700 dark:bg-red-500/10 dark:text-red-300"
                role="alert"
            ></div>

            {{-- Live camera preview --}}
            <div x-show="cameraOn" class="mb-4 overflow-hidden rounded-xl border border-black/[0.08] dark:border-white/10">
                <video
                    data-camera-preview
                    x-ref="preview"
                    autoplay
                    playsinline
                    muted
                    class="max-h-56 w-full bg-black object-cover"
                ></video>
            </div>

            {{-- Transcript --}}
            <div class="min-h-[120px] rounded-xl bg-[#f7f7f8] px-4 py-3 text-sm leading-relaxed dark:bg-[#2f2f2f]">
                <p class="whitespace-pre-wrap text-[#0d0d0d] dark:text-[#ececec]">
                    <span x-text="committed"></span><span
                        class="text-black/40 dark:text-white/40"
                        x-show="interim"
                        x-text="interim"
                    ></span><span
                        x-show="listening"
                        class="ml-0.5 inline-block h-4 w-0.5 animate-pulse bg-black/60 align-middle dark:bg-white/60"
                    ></span>
                    <span
                        x-show="!committed && !interim && !listening"
                        class="text-black/40 dark:text-white/40"
                    >Nothing said yet. Press the microphone and speak.</span>
                </p>
            </div>

            {{-- Word timings, when the engine reports them. A low-confidence word
                 is dimmed rather than removed: it is still the user's speech. --}}
            <div x-show="words.length > 0" class="mt-3 flex flex-wrap gap-1.5">
                <template x-for="(word, index) in words" :key="index">
                    <span
                        class="rounded-md bg-black/[0.06] px-1.5 py-0.5 text-xs text-black/70 dark:bg-white/10 dark:text-white/80"
                        :title="word.start.toFixed(2) + 's – ' + word.end.toFixed(2) + 's'"
                        :style="word.confidence !== null && word.confidence < 0.5 ? 'opacity:.55' : ''"
                        x-text="word.text"
                    ></span>
                </template>
            </div>

            {{-- Scene description --}}
            <div
                x-show="scene"
                class="mt-4 rounded-xl border border-black/[0.08] px-4 py-3 text-sm text-black/80 dark:border-white/10 dark:text-white/80"
            >
                <p class="mb-1 text-xs font-medium uppercase tracking-wide text-black/40 dark:text-white/40">Camera</p>
                <p class="whitespace-pre-wrap" x-text="scene"></p>
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-2 border-t border-black/[0.08] px-5 py-4 dark:border-white/10">
            <button
                type="button"
                x-on:click="toggleListening()"
                :aria-pressed="listening"
                class="inline-flex items-center gap-2 rounded-full px-4 py-2 text-sm font-medium transition"
                :class="listening
                    ? 'bg-red-600 text-white hover:bg-red-700'
                    : 'bg-[#0d0d0d] text-white hover:bg-black/85 dark:bg-[#ececec] dark:text-[#0d0d0d] dark:hover:bg-white'"
            >
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M12 18.75a6 6 0 0 0 6-6v-1.5m-6 7.5a6 6 0 0 1-6-6v-1.5m6 7.5v3.75m-3.75 0h7.5M12 15.75a3 3 0 0 1-3-3V5.25a3 3 0 0 1 6 0v7.5a3 3 0 0 1-3 3Z"/>
                </svg>
                <span x-text="listening ? 'Stop' : 'Record'"></span>
            </button>

            <button
                type="button"
                x-on:click="toggleCamera()"
                :aria-pressed="cameraOn"
                class="inline-flex items-center gap-2 rounded-full border border-black/[0.08] px-4 py-2 text-sm font-medium transition hover:bg-black/[0.04] dark:border-white/10 dark:hover:bg-white/10"
                :class="cameraOn ? 'ring-2 ring-[#0d0d0d] dark:ring-[#ececec]' : ''"
            >
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="m15.75 10.5 4.72-4.72a.75.75 0 0 1 1.28.53v11.38a.75.75 0 0 1-1.28.53l-4.72-4.72M4.5 18.75h9a2.25 2.25 0 0 0 2.25-2.25v-9a2.25 2.25 0 0 0-2.25-2.25h-9A2.25 2.25 0 0 0 2.25 7.5v9a2.25 2.25 0 0 0 2.25 2.25Z"/>
                </svg>
                <span x-text="cameraOn ? 'Camera on' : 'Camera'"></span>
            </button>

            <button
                type="button"
                x-show="cameraOn"
                x-on:click="look()"
                :disabled="busy"
                class="inline-flex items-center gap-2 rounded-full border border-black/[0.08] px-4 py-2 text-sm font-medium transition hover:bg-black/[0.04] disabled:opacity-50 dark:border-white/10 dark:hover:bg-white/10"
                x-text="busy ? 'Looking…' : 'What do you see?'"
            ></button>

            <div class="ml-auto flex items-center gap-2">
                <button
                    type="button"
                    x-on:click="close()"
                    class="rounded-full px-4 py-2 text-sm font-medium text-black/60 transition hover:bg-black/[0.06] dark:text-white/60 dark:hover:bg-white/10"
                >Cancel</button>
                <button
                    type="button"
                    x-on:click="insert()"
                    :disabled="!committed"
                    class="rounded-full bg-[#0d0d0d] px-4 py-2 text-sm font-medium text-white transition hover:bg-black/85 disabled:opacity-40 dark:bg-[#ececec] dark:text-[#0d0d0d]"
                >Insert into message</button>
            </div>
        </div>
    </div>
</div>
</div>

@once
<script>
document.addEventListener('alpine:init', () => {
    /**
     * Live speech and camera capture.
     *
     * Live speech is recorded as short clips and posted as they complete, so the
     * transcript fills in while the user is still talking. Each clip is only ever
     * appended once: a clip that fails is dropped and reported, never retried into
     * a duplicated sentence.
     */
    Alpine.data('liveCapture', () => ({
        open: false,
        listening: false,
        cameraOn: false,
        busy: false,
        committed: '',
        interim: '',
        words: [],
        scene: '',
        error: '',
        status: 'Checking…',
        engine: null,
        language: 'en',
        maxSeconds: 30,

        recorder: null,
        stream: null,
        cameraStream: null,
        chunkIndex: 0,
        chunkTimer: null,

        /**
         * Learn what the server supports before the user starts talking.
         */
        async init() {
            try {
                const response = await fetch('{{ route('chat.capture') }}', {
                    headers: { Accept: 'application/json' },
                });

                const capabilities = await response.json();

                this.engine = capabilities.engine;
                this.language = capabilities.language ?? 'en';
                this.maxSeconds = capabilities.max_seconds ?? 30;
                this.status = this.engine === 'none'
                    ? 'No speech engine is configured'
                    : `Ready · ${this.engine}`;
            } catch {
                this.status = 'Could not reach the server';
            }
        },

        async toggleListening() {
            this.listening ? await this.stopListening() : await this.startListening();
        },

        /**
         * Open the panel.
         *
         * Focus is parked on the panel container rather than left on the
         * trigger: a dialog that opens without moving focus is a dialog a
         * keyboard user has to hunt for.
         */
        show() {
            this.open = true;

            // Two rAFs, not one: opening hides the trigger, and a button that
            // goes display:none while it holds focus drops the caret to the
            // body. Parking focus on the panel before that lands loses the
            // caret for good, so wait for the trigger to be gone first.
            requestAnimationFrame(() => {
                requestAnimationFrame(() => this.$refs.panel?.focus());
            });
        },

        /**
         * Begin capturing microphone audio.
         */
        async startListening() {
            this.error = '';

            if (! navigator.mediaDevices?.getUserMedia || typeof MediaRecorder === 'undefined') {
                this.error = 'This browser cannot record audio. Try Chrome, Edge or Safari.';
                return;
            }

            try {
                this.stream = await navigator.mediaDevices.getUserMedia({
                    audio: { echoCancellation: true, noiseSuppression: true, autoGainControl: true },
                });
            } catch {
                this.error = 'Microphone permission was denied.';
                return;
            }

            this.committed = '';
            this.interim = '';
            this.words = [];
            this.chunkIndex = 0;
            this.listening = true;
            this.status = 'Listening…';

            this.startChunk();
        },

        /**
         * Roll a new clip and post it once it completes.
         */
        startChunk() {
            if (!this.listening) {
                return;
            }

            const recorder = new MediaRecorder(this.stream);
            this.recorder = recorder;

            recorder.ondataavailable = (event) => {
                if (event.data && event.data.size > 0) {
                    this.send(event.data);
                }
            };

            // Rolling to the next clip is what makes this live: without this the
            // recorder would run once and then go silent forever.
            recorder.onstop = () => {
                if (this.listening) {
                    this.startChunk();
                }
            };

            // A clip is closed every few seconds: long enough for a sentence to
            // be transcribed as one unit, short enough to feel live.
            recorder.start();
            this.chunkTimer = setTimeout(() => this.endChunk(), 4000);
        },

        /**
         * Finish the current clip so it can be transcribed.
         */
        endChunk() {
            clearTimeout(this.chunkTimer);

            if (this.recorder?.state === 'recording') {
                this.recorder.stop();
            }
        },

        /**
         * Stop recording and flush the last clip.
         */
        async stopListening() {
            this.listening = false;
            this.status = 'Processing…';

            this.endChunk();
            this.stream?.getTracks().forEach((track) => track.stop());
            this.stream = null;

            // Give the final request a moment to land before the panel moves on.
            await new Promise((resolve) => setTimeout(resolve, 150));

            this.status = this.engine ? `Ready · ${this.engine}` : 'Ready';
        },

        /**
         * Post one recorded clip and append what comes back.
         *
         * Committed text is never rewritten, so a slow or duplicated response
         * cannot scramble a sentence the user has already seen.
         */
        async send(blob) {
            const body = new FormData();
            body.append('audio', blob, `chunk-${this.chunkIndex++}.webm`);
            body.append('language', this.language);

            try {
                const response = await fetch('{{ route('chat.transcribe') }}', {
                    method: 'POST',
                    body,
                    headers: { Accept: 'application/json' },
                });

                const data = await response.json();

                if (!response.ok) {
                    this.error = data.message ?? 'Transcription failed.';
                    return;
                }

                this.error = '';
                this.interim = data.text ?? '';

                if (this.interim) {
                    this.committed = `${this.committed} ${this.interim}`.trim();
                    this.interim = '';
                }

                this.words = [...this.words, ...(data.words ?? [])];
            } catch {
                this.error = 'A clip could not be sent.';
            }
        },

        /**
         * Start or stop the camera preview.
         */
        async toggleCamera() {
            if (this.cameraOn) {
                this.stopCamera();
                return;
            }

            try {
                this.cameraStream = await navigator.mediaDevices.getUserMedia({
                    video: { facingMode: 'user', width: { ideal: 1280 } },
                });

                this.cameraOn = true;

                // The preview element may not exist until x-show reveals it.
                this.$nextTick(() => {
                    if (this.$refs.preview) {
                        this.$refs.preview.srcObject = this.cameraStream;
                    }
                });
            } catch {
                this.error = 'Camera permission was denied.';
            }
        },

        /**
         * Release the camera.
         */
        stopCamera() {
            this.cameraOn = false;
            this.scene = '';

            if (this.$refs.preview) {
                this.$refs.preview.srcObject = null;
            }

            this.cameraStream?.getTracks().forEach((track) => track.stop());
            this.cameraStream = null;
        },

        /**
         * Grab a frame and ask the model what it shows.
         */
        async look() {
            if (!this.cameraOn || this.busy) {
                return;
            }

            const video = this.$refs.preview;

            if (!video?.videoWidth) {
                this.error = 'The camera is not ready yet.';
                return;
            }

            this.busy = true;
            this.error = '';

            try {
                const canvas = document.createElement('canvas');
                canvas.width = video.videoWidth;
                canvas.height = video.videoHeight;
                canvas.getContext('2d').drawImage(video, 0, 0);

                const blob = await new Promise((resolve) => canvas.toBlob(resolve, 'image/jpeg', 0.85));

                if (!blob) {
                    this.error = 'The camera frame could not be captured.';
                    return;
                }

                const body = new FormData();
                body.append('frame', blob, 'frame.jpg');

                const response = await fetch('{{ route('chat.camera') }}', {
                    method: 'POST',
                    body,
                    headers: { Accept: 'application/json' },
                });

                const data = await response.json();

                if (!response.ok) {
                    this.error = data.message ?? 'The camera could not be read.';
                } else {
                    this.scene = data.text;
                }
            } catch {
                this.error = 'The camera frame could not be captured.';
            } finally {
                this.busy = false;
            }
        },

        /**
         * Release the camera and microphone, then close the panel.
         */
        close() {
            this.listening = false;
            this.endChunk();
            this.stream?.getTracks().forEach((track) => track.stop());
            this.stream = null;
            this.stopCamera();
            this.open = false;
        },

        /**
         * Hand the transcript to the composer.
         *
         * The panel is closed before the event is dispatched, because the
         * composer focuses its textarea when it hears the transcript. Firing
         * first would move the caret out of an aria-modal that is still on
         * screen, leaving focus outside the open dialog.
         */
        insert() {
            if (!this.committed) {
                return;
            }

            const text = this.committed;
            const words = this.words;

            this.committed = '';
            this.interim = '';
            this.words = [];
            this.scene = '';
            this.close();

            window.dispatchEvent(new CustomEvent('live-transcript', {
                detail: { text, words },
            }));
        },
    }));
});
</script>
@endonce