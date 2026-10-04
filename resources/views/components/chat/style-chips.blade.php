{{--
    Image style chips.

    Bound to the shared `imageStyles` / `imageStyle` names, so the same markup
    works inside the composer, the studio tool pane and the sketch board. The
    "Any" chip is a genuine no-op: selecting it sends the prompt untouched
    rather than implying a look the generator was never asked for.

    Two groups are shown: visual styles first, then edit operations (upscale,
    inpaint and friends) under their own heading, so a transformation is never
    mistaken for a look.
--}}
<div>
    <div class="flex items-center justify-between">
        <span class="text-[11px] font-medium uppercase tracking-wide text-[#8f8f8f]">Style</span>
        <span class="text-[11px] text-[#8f8f8f]" x-text="imageStyles.find((s) => s.id === imageStyle)?.description"></span>
    </div>

    {{-- Visual styles, "Any" leading so the default stays a no-op. --}}
    <div class="whale-scroll mt-1.5 flex gap-1.5 overflow-x-auto pb-1">
        <template x-for="style in imageStyles.filter((s) => s.family !== 'Edit')" :key="style.id">
            <button
                type="button"
                x-on:click="imageStyle = style.id"
                :title="style.description"
                :aria-pressed="imageStyle === style.id ? 'true' : 'false'"
                class="flex shrink-0 items-center gap-1.5 rounded-full border px-2.5 py-1 text-[11px] font-medium
                       transition focus-visible:outline-2 focus-visible:outline-accent"
                :class="imageStyle === style.id
                    ? 'border-accent bg-accent/10 text-accent'
                    : 'border-black/[0.08] text-[#5d5d5d] hover:bg-black/[0.05] dark:border-white/[0.14] dark:text-[#b4b4b4] dark:hover:bg-white/[0.08]'"
            >
                <span class="text-[13px] leading-none" x-text="style.glyph"></span>
                <span x-text="style.label"></span>
            </button>
        </template>
    </div>

    {{-- Edit operations: a transformation applied to an existing image. --}}
    <div class="mt-1 border-t border-black/[0.06] pt-1 dark:border-white/[0.08]">
        <span class="text-[10px] font-medium uppercase tracking-wide text-[#8f8f8f]">Edit</span>
        <div class="whale-scroll mt-1 flex gap-1.5 overflow-x-auto pb-1">
            <template x-for="style in imageStyles.filter((s) => s.family === 'Edit')" :key="style.id">
                <button
                    type="button"
                    x-on:click="imageStyle = style.id"
                    :title="style.description"
                    :aria-pressed="imageStyle === style.id ? 'true' : 'false'"
                    class="flex shrink-0 items-center gap-1.5 rounded-full border px-2.5 py-1 text-[11px] font-medium
                           transition focus-visible:outline-2 focus-visible:outline-accent"
                    :class="imageStyle === style.id
                        ? 'border-accent bg-accent/10 text-accent'
                        : 'border-black/[0.08] text-[#5d5d5d] hover:bg-black/[0.05] dark:border-white/[0.14] dark:text-[#b4b4b4] dark:hover:bg-white/[0.08]'"
                >
                    <span class="text-[13px] leading-none" x-text="style.glyph"></span>
                    <span x-text="style.label"></span>
                </button>
            </template>
        </div>
    </div>
</div>
