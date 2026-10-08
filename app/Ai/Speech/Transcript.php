<?php

namespace App\Ai\Speech;

/**
 * The outcome of transcribing a stretch of audio.
 *
 * Both engines report the same shape so the browser can render a transcript
 * without caring which one produced it.
 */
class Transcript
{
    /**
     * @param  list<array{text: string, start: float, end: float, confidence: float|null}>  $words
     */
    public function __construct(
        public readonly string $text,
        public readonly string $engine,
        public readonly ?string $language = null,
        public readonly array $words = [],
        public readonly float $durationSeconds = 0.0,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'text' => $this->text,
            'engine' => $this->engine,
            'language' => $this->language,
            'words' => $this->words,
            'duration_seconds' => round($this->durationSeconds, 3),
            'word_count' => count($this->words),
        ];
    }
}
