<?php

namespace App\Ai;

/**
 * The visual styles and edit operations the image tools can be asked for.
 *
 * A style is not a separate model — it is a direction appended to the prompt,
 * so it works on every image provider and costs nothing when unset. Leaving it
 * null sends the prompt untouched, which keeps the default honest: the app
 * never claims a look it did not ask for.
 *
 * Two groups are offered and kept separate:
 *
 *   - Styles (Photo / Drawn / Design / Art) describe the look a generated
 *     image should have.
 *   - Edit operations (Edit) describe a transformation to perform on an
 *     existing image: upscale, inpaint, outpaint, colourise and so on.
 *
 * Every case is covered by every match below, so the catalog served to the
 * browser can never crash on a case that forgot its label. The descriptions
 * and directive text are also served to the browser so the chips in the
 * composer and the studio read exactly what the server will do.
 */
enum ImageStyle: string
{
    /**
     * Styles — the look of a generated image.
     */
    case Realistic = 'realistic';
    case Cartoon = 'cartoon';
    case Logo = 'logo';
    case Animation = 'animation';
    case Illustration = 'illustration';
    case Pixel = 'pixel';
    case LineArt = 'line-art';
    case Watercolor = 'watercolor';
    case Render3D = '3d';
    case Neon = 'neon';
    case Sticker = 'sticker';
    case Pencil = 'pencil';

    /**
     * Edit operations — a transformation applied to an existing image.
     */
    case Upscale = 'upscale';
    case Inpaint = 'inpaint';
    case Outpaint = 'outpaint';
    case Variation = 'variation';
    case ImageToImage = 'image-to-image';
    case ImageVariation = 'image-variation';
    case RemoveBackground = 'remove-background';
    case Colorize = 'colorize';

    /**
     * Whether this entry transforms an existing image rather than
     * describing a look the generator should aim for.
     */
    public function isEdit(): bool
    {
        return $this->family() === 'Edit';
    }

    /**
     * The label shown on the chip.
     */
    public function label(): string
    {
        return match ($this) {
            self::Realistic => 'Realistic',
            self::Cartoon => 'Cartoon',
            self::Logo => 'Logo',
            self::Animation => 'Animation',
            self::Illustration => 'Illustration',
            self::Pixel => 'Pixel art',
            self::LineArt => 'Line art',
            self::Watercolor => 'Watercolour',
            self::Render3D => '3D render',
            self::Neon => 'Neon',
            self::Sticker => 'Sticker',
            self::Pencil => 'Pencil sketch',
            self::Upscale => 'Upscale',
            self::Inpaint => 'Inpaint',
            self::Outpaint => 'Outpaint',
            self::Variation => 'Variation',
            self::ImageToImage => 'Image to image',
            self::ImageVariation => 'Image variation',
            self::RemoveBackground => 'Remove background',
            self::Colorize => 'Colourise',
        };
    }

    /**
     * A one-line description of the transformation, shown under the label.
     */
    public function description(): string
    {
        return match ($this) {
            self::Realistic => 'Photographic, natural light and true-to-life detail.',
            self::Cartoon => 'Bold outlines, flat colour, exaggerated shapes.',
            self::Logo => 'Clean brand mark on a plain background, no clutter.',
            self::Animation => 'Anime cel look, expressive and cinematic.',
            self::Illustration => 'Flat vector-style illustration, poster-ready.',
            self::Pixel => 'Low-resolution pixel art with a limited palette.',
            self::LineArt => 'Single-weight ink outline, no shading.',
            self::Watercolor => 'Painted washes with soft bleeding edges.',
            self::Render3D => 'Modelled and lit like a product render.',
            self::Neon => 'Night palette, glow, high contrast, cyberpunk mood.',
            self::Sticker => 'Die-cut sticker with a thick white border.',
            self::Pencil => 'Graphite drawing with visible hatching.',
            self::Upscale => 'Increase the resolution and fine detail of an image.',
            self::Inpaint => 'Fill in a masked or missing area of the image.',
            self::Outpaint => 'Extend the image beyond its original borders.',
            self::Variation => 'A close variation: same subject, fresh detail.',
            self::ImageToImage => 'Re-render an existing image following the prompt.',
            self::ImageVariation => 'A new version of an image with the same layout.',
            self::RemoveBackground => 'Isolate the subject on a clean background.',
            self::Colorize => 'Add natural colour to a black-and-white image.',
        };
    }

    /**
     * The group the picker sections the chips under.
     */
    public function family(): string
    {
        return match ($this) {
            self::Realistic => 'Photo',
            self::Cartoon, self::Animation, self::Sticker, self::Neon => 'Drawn',
            self::Logo, self::Illustration, self::Pixel, self::LineArt => 'Design',
            self::Watercolor, self::Render3D, self::Pencil => 'Art',
            self::Upscale,
            self::Inpaint,
            self::Outpaint,
            self::Variation,
            self::ImageToImage,
            self::ImageVariation,
            self::RemoveBackground,
            self::Colorize => 'Edit',
        };
    }

    /**
     * The short glyph printed on the chip.
     */
    public function glyph(): string
    {
        return match ($this) {
            self::Realistic => '◎',
            self::Cartoon => '★',
            self::Logo => '◆',
            self::Animation => '◐',
            self::Illustration => '▣',
            self::Pixel => '▦',
            self::LineArt => '✎',
            self::Watercolor => '❍',
            self::Render3D => '◈',
            self::Neon => '✧',
            self::Sticker => '✦',
            self::Pencil => '✎',
            self::Upscale => '⟳',
            self::Inpaint => '✚',
            self::Outpaint => '⤢',
            self::Variation => '✪',
            self::ImageToImage => '⇄',
            self::ImageVariation => '≈',
            self::RemoveBackground => '✖',
            self::Colorize => '▨',
        };
    }

    /**
     * The instruction appended to the prompt for this entry.
     */
    public function directive(): string
    {
        return match ($this) {
            self::Realistic => 'Photorealistic photograph, natural lighting, true-to-life materials and depth of field, shot on a full-frame camera with an 85mm lens, no illustration or painterly texture.',
            self::Cartoon => 'Bold cartoon style: thick clean outlines, flat saturated colour, simple shapes, exaggerated proportions, cheerful and readable at small sizes.',
            self::Logo => 'Professional logo on a plain neutral background. Minimal, geometric, symmetrical, single focal mark, strong negative space, no text unless asked for, no gradients or drop shadows, flat vector appearance.',
            self::Animation => 'Anime cel-shaded animation still: clean linework, flat shading with hard highlights, expressive character design, cinematic framing, background art in the style of a modern animated feature.',
            self::Illustration => 'Flat modern illustration: simple geometric forms, limited palette, soft shapes, generous negative space, poster-like composition, no photorealism.',
            self::Pixel => '16-bit pixel art, chunky visible pixels, strictly limited palette, crisp edges, no anti-aliasing, classic retro game sprite aesthetic.',
            self::LineArt => 'Single-weight line drawing, pure black ink on white, no shading, no fills, no hatching, confident continuous contour lines, technical drawing clarity.',
            self::Watercolor => 'Watercolour painting on cold-press paper: translucent washes, soft bleeding edges, visible pigment granulation, a little white space left bare.',
            self::Render3D => 'Photorealistic 3D render: modelled geometry, physically based materials, soft studio lighting, subtle contact shadows, shallow depth of field, octane-style output.',
            self::Neon => 'Neon cyberpunk night scene: glowing signage, wet reflections, magenta and cyan light, deep shadows, high contrast, atmospheric haze.',
            self::Sticker => 'Die-cut sticker design: bold subject, thick white border around the whole shape, flat colours, simple background, glossy vinyl finish.',
            self::Pencil => 'Graphite pencil drawing on textured paper: soft graphite tones, visible hatching and cross-hatching, no ink, no colour.',
            self::Upscale => 'Upscale: increase the resolution and fine detail while preserving composition, colour and identity; no added artifacts.',
            self::Inpaint => 'Inpaint: reconstruct the masked or missing region so it blends seamlessly with the surrounding image in subject, light and grain.',
            self::Outpaint => 'Outpaint: extend the canvas beyond its original borders, continuing the scene naturally in the same style and lighting.',
            self::Variation => 'Produce a close variation of the source image: same subject, composition and style, with natural small differences.',
            self::ImageToImage => 'Re-render the source image following the prompt while preserving its composition, subject and framing.',
            self::ImageVariation => 'Generate a new version of the source image with the same layout and subject but fresh detail and styling.',
            self::RemoveBackground => 'Remove the background, leaving the subject cleanly isolated on a transparent or plain white background with clean edges.',
            self::Colorize => 'Colourise the source image with natural, plausible colours, preserving tone, grain and detail.',
        };
    }

    /**
     * Apply the entry to a prompt, leaving it untouched when no style is set.
     */
    public static function apply(?string $prompt, ?string $style): string
    {
        $resolved = self::tryFrom((string) $style);

        if ($resolved === null) {
            return (string) $prompt;
        }

        $verb = $resolved->isEdit() ? 'Edit' : 'Style';

        return trim((string) $prompt).' — '.$verb.': '.$resolved->directive();
    }

    /**
     * The catalog sent to the browser. An "any" entry leads so the default
     * stays a genuine no-op rather than an implied style. Styles are listed
     * before edit operations so the picker can present them as two groups.
     *
     * @return list<array{id: string, label: string, description: string, family: string, glyph: string}>
     */
    public static function toArray(): array
    {
        $styles = [[
            'id' => 'any',
            'label' => 'Any',
            'description' => 'No style added — the prompt is sent exactly as written.',
            'family' => 'Any',
            'glyph' => '⋯',
        ]];

        foreach (self::cases() as $style) {
            $styles[] = [
                'id' => $style->value,
                'label' => $style->label(),
                'description' => $style->description(),
                'family' => $style->family(),
                'glyph' => $style->glyph(),
            ];
        }

        return $styles;
    }
}
