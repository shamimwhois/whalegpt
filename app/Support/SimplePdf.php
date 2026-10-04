<?php

namespace App\Support;

/**
 * A minimal, dependency-free PDF writer.
 *
 * It renders Helvetica text onto US-Letter pages: enough for exporting a
 * conversation transcript as a real PDF without pulling a rendering library
 * into the project. Text is word-wrapped, paginated, encoded to Windows-1252
 * (the encoding the base-14 fonts understand) and escaped so parentheses or
 * backslashes in user content cannot break the file structure.
 */
final class SimplePdf
{
    /** Page size in PostScript points (US Letter). */
    private const PAGE_WIDTH = 612.0;

    private const PAGE_HEIGHT = 792.0;

    private const MARGIN = 56.0;

    /**
     * A line ready to be placed on a page.
     *
     * @var list<array{text: string, size: float, bold: bool}>
     */
    private array $lines = [];

    /**
     * Append a paragraph; newlines inside it are honoured and the text is
     * wrapped to the page width.
     */
    public function add(string $text, bool $bold = false, float $size = 10.5): void
    {
        $paragraphs = preg_split('/\R/u', $text) ?: [''];

        foreach ($paragraphs as $paragraph) {
            $wrapped = $this->wrap(rtrim($paragraph), $size);

            if ($wrapped === []) {
                $this->lines[] = ['text' => '', 'size' => $size, 'bold' => $bold];

                continue;
            }

            foreach ($wrapped as $line) {
                $this->lines[] = ['text' => $line, 'size' => $size, 'bold' => $bold];
            }
        }
    }

    /**
     * Append vertical breathing room between blocks.
     */
    public function spacer(float $points = 8.0): void
    {
        $this->lines[] = ['text' => '', 'size' => $points, 'bold' => false];
    }

    /**
     * Render the document and return its bytes.
     */
    public function render(): string
    {
        $pages = $this->paginate();

        if ($pages === []) {
            $pages = [[]];
        }

        $objects = [
            1 => '<< /Type /Catalog /Pages 2 0 R >>',
            3 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
            4 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>',
        ];

        $kids = [];
        $number = 5;

        foreach ($pages as $page) {
            $pageObject = $number++;
            $streamObject = $number++;
            $stream = $this->streamFor($page);

            $objects[$pageObject] = sprintf(
                '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] '
                .' /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents %d 0 R >>',
                $streamObject,
            );
            $objects[$streamObject] = '<< /Length '.strlen($stream)." >>\nstream\n".$stream.'endstream';
            $kids[] = $pageObject.' 0 R';
        }

        $objects[2] = '<< /Type /Pages /Kids ['.implode(' ', $kids).'] /Count '.count($kids).' >>';

        ksort($objects);

        $pdf = "%PDF-1.4\n";
        $offsets = [];

        foreach ($objects as $id => $body) {
            $offsets[$id] = strlen($pdf);
            $pdf .= $id." 0 obj\n".$body."\nendobj\n";
        }

        $xref = strlen($pdf);
        $pdf .= 'xref'."\n".'0 '.(count($objects) + 1)."\n";
        $pdf .= "0000000000 65535 f \n";

        foreach ($offsets as $offset) {
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }

        $pdf .= 'trailer'."\n".'<< /Size '.(count($objects) + 1)." /Root 1 0 R >>\n";
        $pdf .= 'startxref'."\n".$xref."\n%%EOF\n";

        return $pdf;
    }

    /**
     * Place the accumulated lines onto pages, top to bottom.
     *
     * @return list<list<array{text: string, size: float, bold: bool, y: float}>>
     */
    private function paginate(): array
    {
        $pages = [];
        $y = self::PAGE_HEIGHT - self::MARGIN;
        $page = [];

        foreach ($this->lines as $line) {
            $leading = $line['text'] === '' ? $line['size'] : $line['size'] * 1.45;

            if ($y - $leading < self::MARGIN && $line['text'] !== '') {
                $pages[] = $page;
                $page = [];
                $y = self::PAGE_HEIGHT - self::MARGIN;
            }

            $y -= $leading;
            $page[] = $line + ['y' => $y];
        }

        $pages[] = $page;

        return $pages;
    }

    /**
     * Build the content stream for one page.
     *
     * @param  list<array{text: string, size: float, bold: bool, y: float}>  $page
     */
    private function streamFor(array $page): string
    {
        $stream = '';

        foreach ($page as $line) {
            if ($line['text'] === '') {
                continue;
            }

            $font = $line['bold'] ? '/F2' : '/F1';
            $stream .= sprintf(
                "BT %s %.2f Tf 1 0 0 1 %.2f %.2f Tm (%s) Tj ET\n",
                $font,
                $line['size'],
                self::MARGIN,
                $line['y'],
                $this->encode($line['text']),
            );
        }

        return $stream;
    }

    /**
     * Word-wrap one paragraph to the characters that fit the page width.
     *
     * @return list<string>
     */
    private function wrap(string $text, float $size): array
    {
        if ($text === '') {
            return [];
        }

        // Helvetica averages roughly half an em per character.
        $max = max(20, (int) floor((self::PAGE_WIDTH - 2 * self::MARGIN) / ($size * 0.5)));

        if (mb_strlen($text) <= $max) {
            return [$text];
        }

        $words = preg_split('/\s+/u', $text) ?: [$text];
        $lines = [];
        $current = '';

        foreach ($words as $word) {
            $candidate = $current === '' ? $word : $current.' '.$word;

            if (mb_strlen($candidate) <= $max) {
                $current = $candidate;

                continue;
            }

            if ($current !== '') {
                $lines[] = $current;
            }

            // A single word longer than the line is hard-split so nothing
            // can push the layout off the page.
            while (mb_strlen($word) > $max) {
                $lines[] = mb_substr($word, 0, $max);
                $word = mb_substr($word, $max);
            }

            $current = $word;
        }

        if ($current !== '') {
            $lines[] = $current;
        }

        return $lines;
    }

    /**
     * Encode one line for a base-14 font: Windows-1252 bytes, control
     * characters stripped, and the three characters PDF strings escape.
     */
    private function encode(string $text): string
    {
        $converted = @mb_convert_encoding($text, 'Windows-1252', 'UTF-8');

        if (is_string($converted)) {
            $text = $converted;
        }

        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $text) ?? '';

        return str_replace([chr(92), chr(40), chr(41)], [chr(92).chr(92), chr(92).chr(40), chr(92).chr(41)], $text);
    }
}
