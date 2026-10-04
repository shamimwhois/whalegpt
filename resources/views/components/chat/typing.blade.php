<div
    x-show="isTyping"
    x-transition:enter="transition ease-out duration-200"
    x-transition:enter-start="opacity-0 translate-y-1"
    x-transition:enter-end="opacity-100 translate-y-0"
    class="flex items-center gap-1.5 py-1"
    style="display:none"
>
    <span class="h-1.5 w-1.5 animate-bounce rounded-full bg-[#8f8f8f]"
          style="animation-delay:-0.3s"></span>
    <span class="h-1.5 w-1.5 animate-bounce rounded-full bg-[#8f8f8f]"
          style="animation-delay:-0.15s"></span>
    <span class="h-1.5 w-1.5 animate-bounce rounded-full bg-[#8f8f8f]"></span>
</div>