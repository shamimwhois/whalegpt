---
name: vector-and-media
description: "Generating and editing media in Whale: raster image generation and editing, SVG vector artwork via the art agent, audio/TTS, OCR, and the video storyboard. Use when adding media endpoints, tools, or studio panes."
license: MIT
metadata:
  author: whale
---

# Vector & Media

## What each capability really does

| Capability | Backing | Provider requirement |
| --- | --- | --- |
| Image | `Image::of()->generate()` | OpenAI, Gemini, xAI |
| Image edit | `Image::of()->attachments([$file])` | OpenAI, Gemini |
| Audio / TTS | `Audio::of()->generate()` | OpenAI, ElevenLabs, Mistral |
| OCR | vision agent prompt with an attachment | any vision-capable provider |
| Vector | `ArtAgent` writes SVG into the workspace | any text provider |
| Video | stills composed into an HTML storyboard | any image provider |

## Honesty about capabilities

The SDK has **no text-to-video provider**. The video feature generates a
consistent sequence of frames and writes a playable HTML storyboard into the
workspace. Never present this as a true video model, and never silently return
a placeholder when a provider is missing — return a 503 with a message naming
the capability that needs configuring.

## Vector art

Text-to-image models return rasters, which cannot be edited as vectors. Route
vector requests to `ArtAgent`, which writes real SVG markup. Requirements for
good output:
- valid standalone SVG with `xmlns` and a `viewBox`;
- a small, deliberate palette;
- readable markup with a `<title>`;
- no embedded rasters or external fonts.

The result is openable in the code editor and the live preview, and scales
without loss.

## Studio

The studio is a split pane: preview on one side, controls and an explanation of
the capability on the other. Keep the explanation honest about what the tool
does and what it needs configured.
