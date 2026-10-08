<?php

use App\Ai\Speech\AudioNormalizer;
use App\Ai\Speech\Transcriber;
use App\Ai\Speech\WhistleTranscriber;
use Illuminate\Http\UploadedFile;
use Laravel\Ai\AnonymousAgent;
use Laravel\Ai\Contracts\Files\TranscribableAudio;
use Laravel\Ai\Prompts\TranscriptionPrompt;
use Laravel\Ai\Responses\Data\Meta;
use Laravel\Ai\Responses\Data\TranscriptionSegment;
use Laravel\Ai\Responses\Data\TranscriptionUsage;
use Laravel\Ai\Responses\TranscriptionResponse;
use Laravel\Ai\Transcription;
use Tests\TestCase;

pest()->use(TestCase::class)->in('Feature');

/**
 * A minimal, valid 16 kHz mono WAV so the normalizer has real bytes to read.
 */
function fakeWav(float $seconds = 0.5): string
{
    $rate = 16000;
    $samples = (int) ($rate * $seconds);
    $data = str_repeat("\x00\x00", $samples);

    return 'RIFF'
        .pack('V', 36 + strlen($data))
        .'WAVEfmt '.pack('VvvVVvv', 16, 1, 1, $rate, $rate * 2, 2, 16)
        .'data'.pack('V', strlen($data))
        .$data;
}

/**
 * Configure a provider that can transcribe, and select the SDK engine.
 *
 * Ollama is the configured default here and it has no transcription capability,
 * so without this every request would be refused by the provider layer.
 */
function withSdkTranscription(): void
{
    config([
        'whale.speech.binary' => null,
        'whale.speech.engine' => 'sdk',
        'ai.default_for_transcription' => 'openai',
        'ai.providers.openai' => [
            'driver' => 'openai',
            'key' => 'test-key',
            'models' => ['transcription' => ['default' => 'whisper-1']],
        ],
    ]);
}

it('reports no engine when nothing can transcribe', function () {
    // Neither Whistle nor a transcription-capable provider is configured, so the
    // panel must say so up front rather than failing on the first recording.
    config([
        'whale.speech.binary' => null,
        'ai.providers' => ['ollama' => ['driver' => 'ollama', 'url' => 'http://127.0.0.1:11434']],
    ]);

    $this->getJson(route('chat.capture'))
        ->assertOk()
        ->assertJsonPath('engines', [])
        ->assertJsonPath('engine', 'none');
});

it('reports the sdk engine once a transcription provider is configured', function () {
    config([
        'whale.speech.binary' => null,
        'ai.providers.openai' => [
            'driver' => 'openai',
            'key' => 'test-key',
            'models' => ['transcription' => ['default' => 'whisper-1']],
        ],
    ]);

    $this->getJson(route('chat.capture'))
        ->assertOk()
        ->assertJsonPath('engines', ['sdk'])
        ->assertJsonPath('engine', 'sdk')
        ->assertJsonPath('max_seconds', 30);
});

it('reports no engine when the chosen one is not installed', function () {
    // A binary that does not exist must not be presented as a working engine.
    config(['whale.speech.engine' => 'whistle', 'whale.speech.binary' => '/nope/needle']);

    $this->getJson(route('chat.capture'))
        ->assertOk()
        ->assertJsonPath('engine', 'none');
});

it('prefers whistle when the binary is installed', function () {
    $binary = sys_get_temp_dir().'/whale-needle-'.getmypid().'.exe';
    file_put_contents($binary, 'binary');

    config(['whale.speech.binary' => $binary, 'whale.speech.engine' => 'auto']);

    expect(app(WhistleTranscriber::class)->available())->toBeTrue()
        ->and(app(Transcriber::class)->engine())->toBe('whistle');

    File::delete($binary);
});

it('transcribes a recording through the sdk engine', function () {
    withSdkTranscription();

    Transcription::fake(['turn off the kitchen lights']);

    $response = $this->post(route('chat.transcribe'), [
        'audio' => UploadedFile::fake()->createWithContent('clip.wav', fakeWav()),
    ]);

    // Surfaced in the assertion so a failure names the real cause.
    expect($response->status())->toBe(200, $response->getContent());

    $response->assertOk()
        ->assertJsonPath('text', 'turn off the kitchen lights')
        ->assertJsonPath('engine', 'sdk');

    // The fake records the prompt so the audio path and language can be asserted.
    Transcription::assertGenerated(function (TranscriptionPrompt $prompt): bool {
        return $prompt->audio instanceof TranscribableAudio;
    });
});

it('passes the detected language through to the engine', function () {
    withSdkTranscription();

    Transcription::fake(['guten tag']);

    $this->post(route('chat.transcribe'), [
        'audio' => UploadedFile::fake()->createWithContent('clip.wav', fakeWav()),
        'language' => 'de',
    ])->assertOk()->assertJsonPath('text', 'guten tag');

    Transcription::assertGenerated(
        fn (TranscriptionPrompt $prompt): bool => $prompt->language === 'de'
    );
});

it('returns word timings when the engine reports them', function () {
    withSdkTranscription();

    Transcription::fake([
        new TranscriptionResponse(
            'hello world',
            collect([
                new TranscriptionSegment('hello', 'Speaker 1', 0.0, 0.4),
                new TranscriptionSegment('world', 'Speaker 1', 0.4, 0.9),
            ]),
            new TranscriptionUsage,
            new Meta('test', 'test'),
        ),
    ]);

    $this->post(route('chat.transcribe'), [
        'audio' => UploadedFile::fake()->createWithContent('clip.wav', fakeWav()),
    ])->assertOk()
        ->assertJsonPath('word_count', 2)
        ->assertJsonPath('words.0.text', 'hello')
        ->assertJsonPath('words.1.end', 0.9);
});

it('rejects a transcription with no audio', function () {
    $this->postJson(route('chat.transcribe'), [])
        ->assertStatus(422)
        ->assertJsonValidationErrors('audio');
});

it('rejects an audio file that is too large', function () {
    // The limit is 10 MB, expressed in kilobytes.
    $this->post(route('chat.transcribe'), [
        'audio' => UploadedFile::fake()->create('clip.wav', 11_000, 'audio/wav'),
    ])->assertStatus(422)->assertJsonValidationErrors('audio');
});

it('reads the duration of a wav from its header', function () {
    $path = sys_get_temp_dir().'/whale-duration-'.getmypid().'.wav';
    file_put_contents($path, fakeWav(2.0));

    expect(app(AudioNormalizer::class)->duration($path))->toBe(2.0);

    File::delete($path);
});

it('describes what the camera shows', function () {
    AnonymousAgent::fake(['A coffee cup on a desk next to a laptop.']);

    $this->post(route('chat.camera'), [
        'frame' => fakeImageUpload('frame.jpg'),
    ])->assertOk()->assertJsonPath('text', 'A coffee cup on a desk next to a laptop.');
});

it('reports a clear message when a frame is missing', function () {
    $this->postJson(route('chat.camera'), [])
        ->assertStatus(422)
        ->assertJsonValidationErrors('frame');
});

/**
 * The panel and the composer talk over a window event rather than sharing an
 * Alpine scope, so a transcript can be dropped on the floor if either end
 * stops listening. These pin both sides of that contract.
 */
test('the composer listens for a transcript and puts it in the draft', function () {
    $this->get(route('chat.index'))
        ->assertOk()
        ->assertSee("addEventListener('live-transcript'", false)
        ->assertSee('applyLiveTranscript', false);
});

test('a transcript is appended to the draft and the listener is registered once', function () {
    $html = $this->get(route('chat.index'))->assertOk()->getContent();

    // init() runs twice (x-init plus Alpine's own lifecycle hook), so the
    // handler must be dropped before it is registered again, or the same
    // transcript lands in the draft twice.
    // Counted rather than merely present: destroy() also unsubscribes, so a
    // plain `toContain` would still pass with the init() guard deleted, which
    // is the exact regression this guards against.
    expect(substr_count($html, "addEventListener('live-transcript'"))->toBe(1)
        ->and(substr_count($html, "removeEventListener('live-transcript'"))->toBe(2);

    // The drop has to be conditional. Counting the removeEventListener call
    // cannot prove it: `if (false) {` leaves the line in place, the count
    // still reads 2, and the double registration returns.
    expect($html)->toContain('if (this.onLiveTranscript) {', false);

    // Appended, never overwritten: a half-written draft must survive a detour
    // through the microphone.
    expect($html)->toContain('this.draft = this.draft.trim()', false);
});

test('the capture panel closes before it hands the transcript over', function () {
    $html = $this->get(route('chat.index'))->assertOk()->getContent();

    // Focusing the composer while the dialog is still on screen would leave the
    // caret outside an open aria-modal.
    $close = strpos($html, 'this.close();');
    $dispatch = strpos($html, "dispatchEvent(new CustomEvent('live-transcript'");

    expect($close)->toBeInt()
        ->and($dispatch)->toBeInt()
        ->and($close)->toBeLessThan($dispatch);
});

test('the microphone trigger sits in the composer, just before send', function () {
    $composer = file_get_contents(resource_path('views/components/chat/composer.blade.php'));

    // It fills this box, so it belongs with the box's own controls rather than
    // floating over the page.
    expect($composer)->toContain('<x-chat.live-capture />', false);

    // Exactly once: rendering it on the page as well would put two microphones
    // in front of the user, both opening the same panel.
    $page = file_get_contents(resource_path('views/chat/index.blade.php'));
    expect($page)->not->toContain('<x-chat.live-capture />', false);

    // Before the send button, which is what was asked for.
    $trigger = strpos($composer, '<x-chat.live-capture />');
    $send = strpos($composer, 'aria-label="Send message"');

    expect($trigger)->toBeInt()
        ->and($send)->toBeInt()
        ->and($trigger)->toBeLessThan($send);
});

test('the capture panel keeps its original dialog and features', function () {
    $capture = file_get_contents(resource_path('views/components/chat/live-capture.blade.php'));

    // The redesign was reverted: the modal panel, its transcript area, the
    // camera and the explicit insert step are all still here.
    expect($capture)->toContain('aria-modal="true"', false)
        ->and($capture)->toContain('Insert into message', false)
        ->and($capture)->toContain('x-on:click="insert()"', false)
        ->and($capture)->toContain('x-on:click="toggleCamera()"', false)
        ->and($capture)->toContain('x-on:click="look()"', false)
        ->and($capture)->toContain('x-on:click="toggleListening()"', false);

    // And the separate camera component is gone.
    expect(file_exists(resource_path('views/components/chat/camera-capture.blade.php')))->toBeFalse();
});
