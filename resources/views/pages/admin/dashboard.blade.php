@extends('layouts.admin')

@section('title', 'Dashboard')
@section('heading', 'Dashboard')
@section('subheading', 'Live figures from this installation')

@section('content')

    <div class="mb-6">
        <h1 class="text-2xl font-semibold tracking-[-0.03em]">Overview</h1>
        <p class="mt-1 text-sm text-body">
            Every number below is read from the application's own tables, not mocked, so it reflects
            what this install is actually doing right now.
        </p>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-admin.stat-card label="Users" :value="$stats['users']" :delta="$stats['usersNew']" icon="users" />
        <x-admin.stat-card label="Conversations" :value="$stats['conversations']" :delta="$stats['conversationsNew']" icon="list" />
        <x-admin.stat-card label="Messages" :value="$stats['messages']" icon="type" />
        <x-admin.stat-card label="Projects" :value="$stats['projects']" icon="folder" />
    </div>

    <div class="mt-6 grid gap-6 xl:grid-cols-[1.35fr_1fr]">

        {{-- Recent conversations. --}}
        <section class="overflow-hidden rounded-2xl border border-line bg-raised">
            <div class="flex items-center justify-between border-b border-line px-5 py-4">
                <h2 class="text-sm font-semibold">Recent conversations</h2>
                <span class="text-[11px] text-muted">Newest first</span>
            </div>

            @if ($recentConversations->isEmpty())
                <div class="px-5 py-10 text-center text-sm text-muted">No conversations yet.</div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="border-b border-line text-[11px] uppercase tracking-wide text-faint">
                            <tr>
                                <th class="px-5 py-2.5 font-medium">Thread</th>
                                <th class="px-5 py-2.5 font-medium">Owner</th>
                                <th class="px-5 py-2.5 font-medium">Msgs</th>
                                <th class="px-5 py-2.5 font-medium">Updated</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line">
                            @foreach ($recentConversations as $conversation)
                                <tr class="transition hover:bg-wash">
                                    <td class="max-w-[16rem] truncate px-5 py-3 font-medium">{{ $conversation->title ?: 'Untitled' }}</td>
                                    <td class="px-5 py-3 text-body">{{ $conversation->user?->name ?? 'Guest' }}</td>
                                    <td class="px-5 py-3 text-body">{{ $conversation->messages_count }}</td>
                                    <td class="whitespace-nowrap px-5 py-3 text-muted">{{ $conversation->updated_at?->diffForHumans() }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>

        {{-- Recent users. --}}
        <section class="overflow-hidden rounded-2xl border border-line bg-raised">
            <div class="flex items-center justify-between border-b border-line px-5 py-4">
                <h2 class="text-sm font-semibold">Newest users</h2>
                <span class="text-[11px] text-muted">Joined</span>
            </div>

            @if ($recentUsers->isEmpty())
                <div class="px-5 py-10 text-center text-sm text-muted">No users yet.</div>
            @else
                <ul class="divide-y divide-line">
                    @foreach ($recentUsers as $user)
                        <li class="flex items-center gap-3 px-5 py-3">
                            <span class="grid h-8 w-8 shrink-0 place-items-center rounded-full bg-sunken text-xs font-semibold text-body">
                                {{ \Illuminate\Support\Str::of($user->name)->explode(' ')->map(fn ($part) => mb_substr($part, 0, 1))->take(2)->implode('') }}
                            </span>

                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-medium">{{ $user->name }}</p>
                                <p class="truncate text-xs text-muted">{{ $user->email }}</p>
                            </div>

                            @if ($user->isAdmin())
                                <span class="shrink-0 rounded-full bg-accent/10 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-accent">Admin</span>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
    </div>

    {{-- Install configuration. --}}
    <section class="mt-6 overflow-hidden rounded-2xl border border-line bg-raised">
        <div class="border-b border-line px-5 py-4">
            <h2 class="text-sm font-semibold">Install status</h2>
        </div>

        <dl class="grid gap-px bg-line sm:grid-cols-2 lg:grid-cols-3">
            @php
                $statusItems = [
                    ['label' => 'Environment', 'value' => $status['environment']],
                    ['label' => 'Debug mode', 'value' => $status['debug'] ? 'On' : 'Off'],
                    ['label' => 'Database', 'value' => $status['database']],
                    ['label' => 'Sign-in required', 'value' => $status['authRequired'] ? 'Yes' : 'No'],
                    ['label' => 'Administrators', 'value' => (string) $status['admins']],
                    ['label' => 'Speech engine', 'value' => $status['sttEngine'] ?: 'auto'],
                ];
            @endphp

            @foreach ($statusItems as $item)
                <div class="bg-raised px-5 py-4">
                    <dt class="text-[11px] font-medium uppercase tracking-wide text-faint">{{ $item['label'] }}</dt>
                    <dd class="mt-1 text-sm font-medium">{{ $item['value'] }}</dd>
                </div>
            @endforeach
        </dl>
    </section>

@endsection
