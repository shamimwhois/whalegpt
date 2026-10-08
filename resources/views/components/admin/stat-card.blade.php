{{--
    A single headline metric.

        <x-admin.stat-card label="Users" :value="128" :delta="6" icon="users" />

    `delta` is the change over the last seven days. It renders only when given,
    so a metric without a window does not imply one.
--}}
@props([
    'label',
    'value',
    'delta' => null,
    'deltaLabel' => 'in 7 days',
    'icon' => 'grid',
])

<div class="rounded-2xl border border-line bg-raised p-5">
    <div class="flex items-start justify-between gap-3">
        <p class="text-xs font-medium uppercase tracking-wide text-faint">{{ $label }}</p>
        <span class="grid h-8 w-8 place-items-center rounded-lg bg-accent/10 text-accent">
            <x-ui.icon :name="$icon" class="h-4 w-4" />
        </span>
    </div>

    <p class="mt-3 text-3xl font-semibold tracking-[-0.03em]">{{ number_format($value) }}</p>

    @if ($delta !== null)
        <p class="mt-1.5 text-xs text-muted">
            <span class="font-medium {{ $delta > 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-muted' }}">
                {{ $delta > 0 ? '+' : '' }}{{ number_format($delta) }}
            </span>
            {{ $deltaLabel }}
        </p>
    @endif
</div>
