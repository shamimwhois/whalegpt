{{--
    A documentation code sample.

    A plain <pre> in the page palette rather than the chat's highlighted block,
    because the docs are not wired to highlight.js and a fake language strip
    would only promise colour it cannot deliver.

        <x-docs.code lang="bash" label="Request">curl -X POST …</x-docs.code>
--}}
@props([
    'lang' => 'text',
    'label' => null,
])

<figure class="my-5 overflow-hidden rounded-xl border border-line">
    <figcaption class="flex items-center justify-between border-b border-line bg-sunken px-4 py-1.5 text-[11px] font-medium text-muted">
        <span>{{ $label ?? $lang }}</span>
        <span class="font-mono uppercase tracking-wide">{{ $lang }}</span>
    </figcaption>

    <pre class="overflow-x-auto bg-[#0d0d0d] p-4 text-[12.5px] leading-relaxed"><code class="font-mono text-[#ececec]">{{ trim($slot) }}</code></pre>
</figure>
