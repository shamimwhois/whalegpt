{{-- The public footer: the same links the header carries, plus the licence note. --}}
@php
    $groups = [
        'Product' => [
            ['label' => 'Features', 'href' => route('home').'#features'],
            ['label' => 'How it works', 'href' => route('home').'#how'],
            ['label' => 'Open the app', 'href' => route('chat.index')],
        ],
        'Developers' => [
            ['label' => 'Documentation', 'href' => route('docs.index')],
            ['label' => 'Authentication', 'href' => route('docs.show', 'authentication')],
            ['label' => 'Chat completions', 'href' => route('docs.show', 'chat-completions')],
        ],
        'Project' => [
            ['label' => 'Admin', 'href' => route('admin.dashboard')],
        ],
    ];
@endphp

<footer class="border-t border-line bg-sunken">
    <div class="mx-auto grid max-w-6xl gap-10 px-4 py-12 sm:px-6 lg:grid-cols-[1.4fr_1fr_1fr_1fr] lg:px-8">
        <div>
            <x-ui.logo />
            <p class="mt-3 max-w-xs text-sm leading-relaxed text-body">
                A local-first AI assistant for chat, code, research and media — running on the models you already have.
            </p>
        </div>

        @foreach ($groups as $heading => $items)
            <div>
                <p class="text-[12px] font-semibold uppercase tracking-wide text-faint">{{ $heading }}</p>
                <ul class="mt-3 space-y-2">
                    @foreach ($items as $item)
                        <li>
                            <a href="{{ $item['href'] }}" class="text-sm text-body transition hover:text-ink">{{ $item['label'] }}</a>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endforeach
    </div>

    <div class="border-t border-line">
        <div class="mx-auto flex max-w-6xl flex-col gap-2 px-4 py-5 text-xs text-faint sm:flex-row sm:items-center sm:justify-between sm:px-6 lg:px-8">
            <p>&copy; {{ date('Y') }} {{ config('app.name') }}. All rights reserved.</p>
            <p>Built on Laravel.</p>
        </div>
    </div>
</footer>
