{{--
    The one confirmation dialog.

    Deleting a thread used to call window.confirm, which cannot be styled, does
    not match the rest of the app, and on some platforms suppresses the page's
    own focus handling. A single dialog means every destructive action asks the
    same question the same way.

    The caller sets `confirmDialog` to a small object and the dialog renders it:

        this.confirmDialog = {
            title: 'Delete chat?',
            body: 'This also removes every message in it.',
            confirmLabel: 'Delete',
            danger: true,
            onConfirm: () => this.deleteConversation(chat),
        };

    `onConfirm` is called and the dialog closes either way, so a caller never
    has to remember to dismiss it.
--}}
<div
    x-show="confirmDialog"
    x-transition:enter="transition ease-out duration-200"
    x-transition:enter-start="opacity-0"
    x-transition:enter-end="opacity-100"
    x-transition:leave="transition ease-in duration-150"
    x-transition:leave-start="opacity-100"
    x-transition:leave-end="opacity-0"
    class="fixed inset-0 z-[60] flex items-center justify-center p-4"
    style="display:none"
    role="alertdialog"
    aria-modal="true"
    aria-labelledby="whale-confirm-title"
    x-on:keydown.escape.window="confirmDialog = null"
>
    <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" x-on:click="confirmDialog = null"></div>

    <div
        class="whale-pop relative w-full max-w-sm p-0 shadow-2xl"
        x-on:click.outside="confirmDialog = null"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="scale-95 opacity-0"
        x-transition:enter-end="scale-100 opacity-100"
    >
        <div class="p-4">
            <h2 id="whale-confirm-title" class="text-sm font-semibold" x-text="confirmDialog?.title"></h2>
            <p class="mt-1.5 text-[13px] leading-relaxed text-body" x-text="confirmDialog?.body"></p>
        </div>

        <div class="flex justify-end gap-2 border-t border-line p-3">
            <button
                type="button"
                x-on:click="confirmDialog = null"
                class="rounded-lg px-3 py-1.5 text-[13px] font-medium text-body transition
                       hover:bg-wash focus-visible:outline-2 focus-visible:outline-accent"
            >
                Cancel
            </button>

            <button
                type="button"
                x-on:click="runConfirm()"
                :disabled="confirmDialog?.busy"
                class="rounded-lg px-3 py-1.5 text-[13px] font-medium text-white transition
                       focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent
                       disabled:cursor-not-allowed disabled:opacity-50"
                :class="confirmDialog?.danger
                    ? 'bg-rose-600 hover:bg-rose-700'
                    : 'bg-ink hover:opacity-90'"
                x-text="confirmDialog?.busy ? 'Working…' : (confirmDialog?.confirmLabel ?? 'Confirm')"
            ></button>
        </div>
    </div>
</div>