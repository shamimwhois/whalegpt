// Temporary live-browser check. Drives a real headless Chrome over the
// DevTools Protocol and asserts the composer behaves: suggestion panels,
// toggles, depth/effort pickers, selection toolbar and paste handling.
//
//   php artisan serve --port=8000
//   chrome --headless=new --remote-debugging-port=9223 --user-data-dir=/tmp/whale-cdp
//   node browser-check.mjs
//
// Both endpoints are overridable with CDP_URL and APP_URL.
const CDP = process.env.CDP_URL ?? 'http://127.0.0.1:9223';
const APP = process.env.APP_URL ?? 'http://127.0.0.1:8000/ai/chat';

const target = await (await fetch(`${CDP}/json/new?${encodeURIComponent(APP)}`, { method: 'PUT' })).json();

const ws = new WebSocket(target.webSocketDebuggerUrl);
let id = 0;
const pending = new Map();
const problems = [];

ws.onmessage = (event) => {
    const message = JSON.parse(event.data);

    if (message.method === 'Log.entryAdded' && message.params?.entry?.level === 'error') {
        problems.push(`console: ${message.params.entry.text}`);
    }

    // console.error is how Alpine reports a bad expression, so it has to be
    // collected separately from the Log domain.
    if (message.method === 'Runtime.consoleAPICalled' && message.params?.type === 'error') {
        const text = (message.params.args ?? []).map((arg) => arg.value ?? arg.description ?? '').join(' ');
        problems.push(`console: ${text}`);
    }

    if (message.method === 'Runtime.exceptionThrown') {
        const details = message.params.exceptionDetails;
        problems.push(`exception: ${details.exception?.description ?? details.text} at ${details.url ?? '?'}:${(details.lineNumber ?? -1) + 1}`);
    }

    if (message.id && pending.has(message.id)) {
        pending.get(message.id)(message);
        pending.delete(message.id);
    }
};

await new Promise((resolve) => { ws.onopen = resolve; });

const send = (method, params = {}) => new Promise((resolve) => {
    const requestId = ++id;
    pending.set(requestId, resolve);
    ws.send(JSON.stringify({ id: requestId, method, params }));
});

const evaluate = async (expression) => {
    const response = await send('Runtime.evaluate', { expression, awaitPromise: true, returnByValue: true });

    if (response.result?.exceptionDetails) {
        return { __error: response.result.exceptionDetails.exception?.description ?? 'evaluation failed' };
    }

    return response.result?.result?.value;
};

await send('Runtime.enable');
await send('Log.enable');

// A target opened through /json/new is never the visible tab, so headless
// Chrome is free to skip style and layout work for it. Everything that reads a
// computed style then comes back stale — a CSS custom property updated from
// Alpine looks like it was ignored. Enabling the page and bringing it forward
// makes it render, which is what the DOM-reading checks below assume.
await send('Page.enable');
await send('Page.bringToFront');

// Let the CDN Alpine and the first fetches start. waitFor() below does the
// actual waiting for application state.
await new Promise((resolve) => setTimeout(resolve, 1000));

// A fixed sleep raced the capabilities fetch: the catalog, styles and lengths
// all arrive asynchronously after Alpine boots, so a page that had been up for
// six seconds could still report an empty mention list. Poll for the specific
// state the checks below read instead of guessing at a duration.
const waitFor = async (expression, timeout = 20000) => {
    const startedAt = Date.now();

    while (Date.now() - startedAt < timeout) {
        // Strict comparison: evaluate() returns an error object when the
        // expression throws, and an object is truthy, so anything looser here
        // treats "the page is broken" as "the page is ready".
        if ((await evaluate(expression)) === true) {
            return true;
        }

        await new Promise((resolve) => setTimeout(resolve, 250));
    }

    return false;
};

const appReady = `(() => {
    const el = document.querySelector('[x-data]');
    if (!el || !window.Alpine) return false;

    const data = Alpine.$data(el);

    return data.mentionCatalog.length > 0
        && data.lengths.length > 0
        && data.imageStyles.length > 0;
})()`;

// Alpine is loaded from a CDN, so one navigation can fail for reasons that have
// nothing to do with the application under test — and every check below would
// then fail with "Alpine is not defined". Retry the load before calling the
// page dead, so a single flaky fetch does not produce a wall of red.
let bootReady = false;

for (let attempt = 1; attempt <= 3 && !bootReady; attempt++) {
    bootReady = await waitFor(appReady, 20000);

    if (!bootReady && attempt < 3) {
        await send('Page.reload', { ignoreCache: true });
        await new Promise((resolve) => setTimeout(resolve, 2000));
    }
}

const results = [];
const check = (name, pass, detail = '') => results.push({ name, pass, detail });

check('the capabilities catalog loads', bootReady, `catalog=${bootReady}`);

// Everything below reads Alpine's reactive state, so running it against a page
// that never booted would only bury the real failure under ~90 identical
// "Alpine is not defined" entries.
if (!bootReady) {
    console.log('FAIL  the page never booted into Alpine after 3 load attempts');
    console.log('\n0/1 passed');
    process.exit(1);
}

// --- The page actually booted into Alpine ----------------------------------
const boot = await evaluate(`(() => {
    const el = document.querySelector('[x-data]');
    if (!el || !window.Alpine) return null;
    const data = Alpine.$data(el);
    return {
        mode: data.mode, depth: data.depth, thinking: data.thinking,
        web: data.web, deepSearch: data.deepSearch,
        modes: data.modes.length, depths: data.depths.length, efforts: data.efforts.length,
        mentions: data.mentionCatalog.length,
        greeting: !!document.body.textContent.match(/What can I help with\\?/),
    };
})()`);

check('app boots into Alpine', boot && !boot.__error, JSON.stringify(boot));

// These lists are owned by the server and grow as the application does, so the
// page is measured against the endpoint that produced it. Hardcoding the counts
// here is what made this check report a failure for a page doing exactly what
// it was configured to do.
const origin = (process.env.APP_URL ?? 'http://127.0.0.1:8000').replace(/\/$/, '');
const capabilities = await fetch(`${origin}/ai/chat/capabilities`)
    .then((response) => (response.ok ? response.json() : null))
    .catch(() => null);

const serverCount = (key) => capabilities?.[key]?.length ?? null;

if (boot) {
    check('every server mode reaches the composer',
        boot.modes === serverCount('modes'),
        `page=${boot.modes} server=${serverCount('modes')}`);
    check('every response depth reaches the composer',
        boot.depths === serverCount('depths'),
        `page=${boot.depths} server=${serverCount('depths')}`);
    check('every thinking effort reaches the composer',
        boot.efforts === serverCount('efforts'),
        `page=${boot.efforts} server=${serverCount('efforts')}`);
    check('sub-agents are mentionable', boot.mentions >= 6, `mentions=${boot.mentions}`);
    check('empty state greeting is visible', boot.greeting);
    check('search toggles default off', boot.web === false && boot.deepSearch === false,
        `web=${boot.web} deep=${boot.deepSearch}`);
}

// --- Slash commands --------------------------------------------------------
const slash = await evaluate(`(() => {
    const data = Alpine.$data(document.querySelector('[x-data]'));
    const ta = document.querySelector('textarea[x-ref="input"]');
    ta.value = '/dee'; ta.selectionStart = ta.selectionEnd = ta.value.length;
    data.draft = ta.value;
    data.syncSuggestions({ target: ta });
    return { open: data.slashOpen, matches: data.slashMatches().map((c) => c.id) };
})()`);

check('typing "/" opens the command panel', slash?.open === true, JSON.stringify(slash));
check('"/dee" narrows to the deep-search command',
    slash?.matches?.[0] === 'deep-search', JSON.stringify(slash?.matches));

// --- @-mentions ------------------------------------------------------------
const mention = await evaluate(`(async () => {
    const data = Alpine.$data(document.querySelector('[x-data]'));
    const ta = document.querySelector('textarea[x-ref="input"]');
    ta.value = '@coding'; ta.selectionStart = ta.selectionEnd = ta.value.length;
    data.draft = ta.value;
    data.syncSuggestions({ target: ta });
    const open = data.mentionOpen;
    const list = data.mentionMatches();
    const first = list[0]?.id;
    data.applyMention(list[0]);
    await Alpine.nextTick();
    return { open, first, draft: data.draft };
})()`);

check('"@" opens the mention panel', mention?.open === true, JSON.stringify(mention));
check('the coding agent is offered first', mention?.first === 'coding_agent', mention?.first);
check('choosing a mention inserts it into the draft', mention?.draft === '@coding_agent ', mention?.draft);

// --- Selection toolbar -----------------------------------------------------
const selection = await evaluate(`(() => {
    const data = Alpine.$data(document.querySelector('[x-data]'));
    const ta = document.querySelector('textarea[x-ref="input"]');
    data.draft = 'make this bold please';
    ta.value = data.draft;
    ta.selectionStart = 5; ta.selectionEnd = 9;
    data.syncSelection({ target: ta });
    const before = data.selectionLength;
    data.wrapSelection('**');
    const after = data.draft;
    data.draft = ''; data.syncSelection({ target: document.querySelector('textarea[x-ref="input"]') });
    return { before, after };
})()`);

check('selecting text reports its length', selection?.before === 4, JSON.stringify(selection));
check('the selection toolbar wraps the selection', selection?.after === 'make **this** bold please', selection?.after);

// --- Toggles + pickers -----------------------------------------------------
const controls = await evaluate(`(async () => {
    const data = Alpine.$data(document.querySelector('[x-data]'));
    data.web = true;
    data.deepSearch = true;
    data.setEffort('xhigh');
    data.setDepth('deep');
    data.mode = 'deep-search';

    // Alpine rewrites the DOM on nextTick, so the attribute has to be read
    // after the flush rather than in the same tick as the assignment.
    await Alpine.nextTick();

    const searchPressed = document.querySelector('[title="Allow the assistant to search the web"]').getAttribute('aria-pressed');
    const deepPressed = document.querySelector('[title="Plan several queries and cross-check the sources"]').getAttribute('aria-pressed');

    return {
        web: data.web, deep: data.deepSearch,
        effort: data.effortLabel(), depth: data.depthLabel(), mode: data.modeLabel(),
        searchPressed, deepPressed,
    };
})()`);

check('search toggle flips on', controls?.web === true, JSON.stringify(controls));
check('deep-search toggle flips on', controls?.deep === true);
check('thinking effort reads "Extra high"', controls?.effort === 'Extra high', controls?.effort);
check('response depth reads "Deep thinking"', controls?.depth === 'Deep thinking', controls?.depth);
check('assistant mode reads "Deep search"', controls?.mode === 'Deep search', controls?.mode);
check('the search toggle reflects aria-pressed', controls?.searchPressed === 'true', controls?.searchPressed);
check('the deep-search toggle reflects aria-pressed', controls?.deepPressed === 'true', controls?.deepPressed);

// --- Paste: rich HTML is flattened, an image becomes an attachment ---------
const paste = await evaluate(`(() => {
    const data = Alpine.$data(document.querySelector('[x-data]'));
    data.draft = '';
    let prevented = false;
    data.handlePaste({
        clipboardData: { items: [], getData: (type) => type === 'text/html' ? '<b>rich</b> text' : 'rich text' },
        preventDefault: () => { prevented = true; },
    });
    const html = data.draft;

    data.draft = '';
    const files = data.pendingFiles.length;
    data.handlePaste({
        clipboardData: { items: [{ kind: 'file', type: 'image/png', getAsFile: () => new File(['x'], 'clip.png', { type: 'image/png' }) }] },
        preventDefault: () => {},
    });
    return { html, prevented, added: data.pendingFiles.length - files };
})()`);

check('rich pasted HTML is flattened to plain text', paste?.html === 'rich text' && paste?.prevented === true,
    JSON.stringify(paste));
check('a pasted image becomes an attachment', paste?.added === 1, JSON.stringify(paste));

// --- Model type filter -----------------------------------------------------
const modelFilter = await evaluate(`(() => {
    const data = Alpine.$data(document.querySelector('[x-data]'));
    const options = data.modelFilterOptions().map((o) => o.id);
    const all = data.visibleModelCount();
    data.modelFilter = 'reasoning';
    const reasoning = data.visibleModelCount();
    data.modelFilter = 'fast';
    const fast = data.visibleModelCount();
    data.modelFilter = 'all';
    return { options, all, reasoning, fast };
})()`);

check('the model type filter offers three kinds',
    JSON.stringify(modelFilter?.options) === JSON.stringify(['all', 'reasoning', 'fast']),
    JSON.stringify(modelFilter));

// --- Response length slider --------------------------------------------------
const length = await evaluate(`(async () => {
    const data = Alpine.$data(document.querySelector('[x-data]'));

    try {
        data.setLengthIndex(0);
        await Alpine.nextTick();

        const auto = { id: data.length, label: data.lengthLabel() };

        data.setLengthIndex(3);
        await Alpine.nextTick();

        const sliders = [].slice.call(document.querySelectorAll('input[type=range]'));
        const slider = sliders.find((input) => input.getAttribute('x-on:input')?.includes('setLengthIndex'));

        const payload = await (await fetch('/ai/chat/capabilities', { headers: { Accept: 'application/json' } })).json();

        return {
            tiers: data.lengths.map((option) => option.id),
            serverTiers: (payload.lengths ?? []).map((option) => option.id),
            auto,
            long: { id: data.length, label: data.lengthLabel(), stored: localStorage.getItem('whale.length') },
            sliderValue: slider?.value,
            sliderMin: slider?.min,
            sliderMax: slider?.max,
        };
    } catch (error) {
        return { __err: String(error) };
    }
})()`);

check('response length offers every tier the server declares',
    JSON.stringify(length?.tiers) === JSON.stringify(length?.serverTiers),
    JSON.stringify(length));

check('response length starts on Auto', length?.auto?.id === 'auto' && length?.auto?.label === 'Auto',
    JSON.stringify(length?.auto));

check('the last slider position selects Long and persists it',
    length?.long?.id === 'long' && length?.long?.stored === 'long',
    JSON.stringify(length?.long));

check('the slider spans every tier',
    length?.sliderMin === '0' && length?.sliderMax === String(length?.tiers?.length - 1) && length?.sliderValue === '3',
    `${length?.sliderMin}..${length?.sliderMax} = ${length?.sliderValue}`);

// --- Sidebar: width, clamping, persistence ----------------------------------
// The width is animated over 300ms, so every measurement below waits for the
// transition to land. Reading on the next tick measures the width the sidebar
// is leaving, not the one it reached.
const settle = 'await new Promise((resolve) => setTimeout(resolve, 450));';

const sidebarWidth = await evaluate(`(async () => {
    const data = Alpine.$data(document.querySelector('[x-data]'));
    const aside = document.querySelector('aside[aria-label="Sidebar"]');

    try {
        // A first visit has no stored key at all, and the default has to stay
        // 260px. Number(null) is 0, which used to clamp straight to the 200px
        // minimum instead.
        localStorage.removeItem('whale.sidebar.width');
        data.sidebarWidth = 300;
        data.applyStoredSidebar();
        ${settle}

        const withoutKey = data.sidebarWidth;

        data.setSidebarWidth(320);
        ${settle}

        return {
            boot: 260,
            withoutKey,
            clampedWidth: Math.round(aside.getBoundingClientRect().width),
            clamps: [data.clampSidebarWidth(50), data.clampSidebarWidth(9999), data.clampSidebarWidth(260)],
            stored: localStorage.getItem('whale.sidebar.width'),
        };
    } catch (error) {
        return { __err: String(error) };
    }
})()`);

check('a missing stored width leaves the width alone instead of clamping to the minimum',
    sidebarWidth?.withoutKey === 300,
    JSON.stringify(sidebarWidth));

check('a chosen width is applied and persisted',
    sidebarWidth?.clampedWidth === 320 && sidebarWidth?.stored === '320',
    JSON.stringify({ width: sidebarWidth?.clampedWidth, stored: sidebarWidth?.stored }));

check('the sidebar width clamps to 200-380',
    JSON.stringify(sidebarWidth?.clamps) === JSON.stringify([200, 380, 260]),
    JSON.stringify(sidebarWidth?.clamps));

// --- Sidebar: collapse, rail, and the mobile drawer -------------------------
const sidebarModes = await evaluate(`(async () => {
    const data = Alpine.$data(document.querySelector('[x-data]'));
    const aside = document.querySelector('aside[aria-label="Sidebar"]');
    const searchField = document.querySelector('aside[aria-label="Sidebar"] input[type=search]');

    try {
        data.sidebarCollapsed = false;
        data.setSidebarWidth(320);

        data.toggleSidebar();
        ${settle}

        const asideBox = aside.getBoundingClientRect();
        const collapsed = {
            state: data.sidebarCollapsed,
            stored: localStorage.getItem('whale.sidebar.collapsed'),
            px: Math.round(asideBox.width),
            searchVisible: searchField.getClientRects().length > 0,
            searchOverflows: searchField.getBoundingClientRect().right > asideBox.right,
            expandButton: !!document.querySelector('aside[aria-label="Sidebar"] [aria-label="Expand sidebar"]'),
        };

        data.toggleSidebar();
        ${settle}

        const expanded = { state: data.sidebarCollapsed, px: Math.round(aside.getBoundingClientRect().width) };

        return { collapsed, expanded };
    } catch (error) {
        return { __err: String(error) };
    }
})()`);

check('collapsing narrows the sidebar to the 56px rail and persists it',
    sidebarModes?.collapsed?.state === true
    && sidebarModes.collapsed.stored === '1'
    && sidebarModes.collapsed.px === 56,
    JSON.stringify(sidebarModes?.collapsed));

check('the rail hides the search field instead of overflowing it',
    sidebarModes?.collapsed?.searchVisible === false && sidebarModes.collapsed.searchOverflows === false,
    JSON.stringify(sidebarModes?.collapsed));

check('the rail keeps a way back open', sidebarModes?.collapsed?.expandButton === true);

check('expanding restores the chosen width',
    sidebarModes?.expanded?.state === false && sidebarModes.expanded.px === 320,
    JSON.stringify(sidebarModes?.expanded));

// The drawer is an overlay on small screens, so a width remembered from the
// desktop must not shrink it there. Only a real viewport change proves it.
await send('Emulation.setDeviceMetricsOverride', { width: 900, height: 900, deviceScaleFactor: 1, mobile: false });

const mobileWidth = await evaluate(`(async () => {
    const data = Alpine.$data(document.querySelector('[x-data]'));
    const aside = document.querySelector('aside[aria-label="Sidebar"]');

    try {
        data.sidebarCollapsed = true;
        ${settle}

        return {
            collapsed: data.sidebarCollapsed,
            px: Math.round(aside.getBoundingClientRect().width),
        };
    } catch (error) {
        return { __err: String(error) };
    }
})()`);

await send('Emulation.clearDeviceMetricsOverride');

check('a collapsed desktop sidebar does not shrink the mobile drawer',
    mobileWidth?.collapsed === true && mobileWidth?.px === 280,
    JSON.stringify(mobileWidth));

// --- Sidebar: grid/list across every list ------------------------------------
const layout = await evaluate(`(async () => {
    const data = Alpine.$data(document.querySelector('[x-data]'));

    try {
        // A project with one chat, so the nested list exists to be inspected.
        if (!data.projects.length) {
            data.projects = [{ id: 'probe-project', name: 'Probe project', conversations: [
                { id: 'probe-chat', title: 'Probe chat', preview: 'Probe preview', updated_at: new Date().toISOString(), updated_human: 'now' },
            ] }];
        }

        data.searchQuery = '';
        data.sidebarCollapsed = false;
        data.setHistoryLayout('grid');
        await Alpine.nextTick();

        const grid = {
            state: data.historyLayout,
            effective: data.isGridHistory(),
            className: data.historyLayoutClass(),
            nested: data.projects[0].conversations.length,
        };

        data.sidebarCollapsed = true;
        await Alpine.nextTick();

        const whileCollapsed = { effective: data.isGridHistory(), className: data.historyLayoutClass() };

        data.sidebarCollapsed = false;
        data.setHistoryLayout('list');
        await Alpine.nextTick();

        return { grid, whileCollapsed, list: data.historyLayoutClass(), stored: localStorage.getItem('whale.history.layout') };
    } catch (error) {
        return { __err: String(error) };
    }
})()`);

check('grid mode is stored and reaches the containers',
    layout?.grid?.state === 'grid' && layout.grid.effective === true
    && layout.grid.className.includes('grid-cols-2'),
    JSON.stringify(layout?.grid));

check('grid mode stands down while the sidebar is a rail',
    layout?.whileCollapsed?.effective === false && ! layout.whileCollapsed.className.includes('grid-cols-2'),
    JSON.stringify(layout?.whileCollapsed));

check('list mode comes back and is persisted',
    layout?.list === 'space-y-0.5' && layout?.stored === 'list',
    `${layout?.list} / ${layout?.stored}`);

// --- Sidebar: date grouping, search scope, and rename -----------------------
const sidebarLogic = await evaluate(`(async () => {
    const data = Alpine.$data(document.querySelector('[x-data]'));
    const midnight = new Date();
    midnight.setHours(0, 0, 0, 0);

    const daysAgo = (days) => new Date(midnight.getTime() - (days - 1) * 86400000).toISOString();

    const original = data.conversations;
    data.conversations = [
        { id: 'g-today', title: 'Today thread', preview: '', updated_at: daysAgo(1), updated_human: 'now' },
        { id: 'g-yesterday', title: 'Yesterday thread', preview: '', updated_at: daysAgo(2), updated_human: 'yesterday' },
        { id: 'g-week', title: 'Week thread', preview: '', updated_at: daysAgo(5), updated_human: '5d' },
        { id: 'g-old', title: 'Old thread', preview: '', updated_at: daysAgo(90), updated_human: '90d' },
    ];

    const groups = data.conversationGroups().map((group) => [group.label, group.chats.length]);

    data.searchQuery = 'today';
    const searchHits = data.conversationGroups().flatMap((group) => group.chats.map((chat) => chat.title));
    data.searchQuery = '';

    // Search has to reach into projects, or a filed chat reads as "not found".
    data.projects = [{ id: 'p1', name: 'Filed', conversations: [
        { id: 'c1', title: 'Nested thread', preview: 'inside a project', updated_at: daysAgo(1), updated_human: 'now' },
    ] }];
    data.searchQuery = 'nested';
    const nested = data.visibleProjectConversations(data.projects[0]).length;
    data.searchQuery = '';

    // Inline rename swaps the row for a field and hands it the caret.
// Inline rename swaps the row for a field and hands it the caret. The field is
// created by an x-if on a later tick than the one that queued the focus, so the
// caret is only measurable once both have flushed.
data.startRename({ id: 'g-today', title: 'Today thread' });
await Alpine.nextTick();
await new Promise((resolve) => setTimeout(resolve, 60));

const renameInput = document.getElementById('rename-g-today');
const renameFocused = document.activeElement === renameInput;
const renameDraft = data.renameDraft;
data.cancelRename();
await Alpine.nextTick();

data.conversations = original;

return {
    groups, searchHits, nested,
    rename: { existed: !! renameInput, focused: renameFocused, draft: renameDraft, cleared: data.renamingId === null },
};
})()`);

check('threads are bucketed into Today, Yesterday, Previous 7 days and Older',
    JSON.stringify(sidebarLogic?.groups) === JSON.stringify([['Today', 1], ['Yesterday', 1], ['Previous 7 days', 1], ['Older', 1]]),
    JSON.stringify(sidebarLogic?.groups));

check('search filters the grouped chats', sidebarLogic?.searchHits?.length === 1, JSON.stringify(sidebarLogic?.searchHits));

check('search reaches chats filed inside a project', sidebarLogic?.nested === 1, String(sidebarLogic?.nested));

check('renaming swaps the row for a focused field, and cancelling restores it',
    sidebarLogic?.rename?.existed === true
    && sidebarLogic.rename.focused === true
    && sidebarLogic.rename.draft === 'Today thread'
    && sidebarLogic.rename.cleared === true,
    JSON.stringify(sidebarLogic?.rename));

// A destructive action names what it will do, then confirms — and the dialog
// closes whether the action succeeded or threw.
const confirm = await evaluate(`(async () => {
    const data = Alpine.$data(document.querySelector('[x-data]'));

    data.askConfirm({
        title: 'Delete "Probe chat"?',
        body: 'Probe body',
        confirmLabel: 'Delete chat',
        danger: true,
        onConfirm: () => {},
    });
    await Alpine.nextTick();
    await new Promise((resolve) => setTimeout(resolve, 250));

    // The overlay is position: fixed, and a fixed element's offsetParent is
    // always null — so visibility has to be read from its client rects.
    const panel = document.querySelector('[role=alertdialog]');
    const buttons = panel ? [].slice.call(panel.querySelectorAll('button')) : [];

    const opened = {
        visible: panel !== null && panel.getClientRects().length > 0,
        title: panel?.querySelector('#whale-confirm-title')?.textContent?.trim(),
        confirmLabel: buttons.length ? buttons[buttons.length - 1].textContent.trim() : null,
    };

    await data.runConfirm();
    await Alpine.nextTick();
    await new Promise((resolve) => setTimeout(resolve, 250));

    const closed = data.confirmDialog === null
        && ! [].some.call(document.querySelectorAll('[role=alertdialog]'), (node) => node.getClientRects().length > 0);

    // A rejected action has to surface and still dismiss, or the dialog
    // strands itself open with nothing to click.
    data.askConfirm({ title: 'Boom', body: 'Boom', onConfirm: () => { throw new Error('boom'); } });
    await Alpine.nextTick();
    await data.runConfirm();
    await Alpine.nextTick();

    return { opened, closed, errorShown: data.lastError === 'boom', dismissed: data.confirmDialog === null };
})()`);

check('the confirm dialog opens with the action named',
    confirm?.opened?.visible === true
    && confirm.opened.title === 'Delete "Probe chat"?'
    && confirm.opened.confirmLabel === 'Delete chat',
    JSON.stringify(confirm?.opened));

check('confirming dismisses the dialog', confirm?.closed === true, JSON.stringify(confirm));

check('a failed action surfaces the error and still dismisses',
    confirm?.errorShown === true && confirm.dismissed === true,
    JSON.stringify({ errorShown: confirm?.errorShown, dismissed: confirm?.dismissed }));

// --- Reading preferences ------------------------------------------------
// These assert the DOM, not just the state: a preference that is stored
// correctly but never reaches the stylesheet would pass a state-only check
// and change nothing on screen.
const preferences = await evaluate(`(async () => {
    const data = Alpine.$data(document.querySelector('[x-data]'));
    const root = document.documentElement;
    const read = (name) => getComputedStyle(root).getPropertyValue(name).trim();
    const row = document.createElement('div');
    row.className = 'whale-reading';
    document.querySelector('[x-ref="scroller"]').appendChild(row);
    const size = async () => {
        // Reading layout forces a flush, but a newly-inserted element can still
        // resolve to its inherited value before the stylesheet and the custom
        // property change have both been applied. Waiting for the next frame
        // guarantees the document has actually painted with the new value.
        void row.offsetHeight;
        await new Promise((resolve) => requestAnimationFrame(resolve));
        void row.offsetHeight;

        return getComputedStyle(row).fontSize;
    };

    const result = { defaults: {}, applied: {}, clamped: {}, stored: {}, sizes: {} };

    // Cleared first so this block asserts the defaults rather than whatever a
    // previous run left behind: the page has already read storage by now, so the
    // stored values are reapplied explicitly rather than assumed.
    for (const key of ['whale.reading.scale', 'whale.transcript.density', 'whale.reduce.motion']) {
        localStorage.removeItem(key);
    }

    data.applyPreferences();
    await Alpine.nextTick();
    result.defaults = {
        scale: data.readingScale,
        density: data.transcriptDensity,
        motion: data.reduceMotion,
        cssScale: read('--reading-scale'),
        cssGap: read('--density-gap'),
        motionClass: root.classList.contains('reduce-motion'),
        fontSize: await size(),
    };

    data.setReadingScale(1.25);
    data.setTranscriptDensity(3);
    data.setReduceMotion(true);
    await Alpine.nextTick();
    result.applied = {
        cssScale: read('--reading-scale'),
        cssGap: read('--density-gap'),
        motionClass: root.classList.contains('reduce-motion'),
        large: await size(),
    };

    // Read before the clamp step below, which overwrites the same keys.
    result.stored = {
        scale: localStorage.getItem('whale.reading.scale'),
        density: localStorage.getItem('whale.transcript.density'),
        motion: localStorage.getItem('whale.reduce.motion'),
    };

    // A hand-edited store must not be able to produce a layout the stylesheet
    // has no rule for, so the setters clamp rather than trusting the input.
    data.setReadingScale(99);
    data.setReadingScale(0.001);
    data.setTranscriptDensity(9999);
    data.setTranscriptDensity(-20);
    await Alpine.nextTick();
    result.clamped = { scale: data.readingScale, density: data.transcriptDensity };

    // A corrupt stored value falls back to the default rather than painting NaN.
    localStorage.setItem('whale.reading.scale', 'not-a-number');
    localStorage.setItem('whale.transcript.density', '');
    data.applyPreferences();
    await Alpine.nextTick();
    result.corrupt = { scale: data.readingScale, density: data.transcriptDensity, cssScale: read('--reading-scale') };

    data.setReadingScale(0.9);
    await Alpine.nextTick();
    result.sizes = { small: await size() };

    data.resetPreferences();
    await Alpine.nextTick();
    result.reset = {
        scale: data.readingScale,
        density: data.transcriptDensity,
        motion: data.reduceMotion,
        cssScale: read('--reading-scale'),
        cssGap: read('--density-gap'),
        motionClass: root.classList.contains('reduce-motion'),
        normal: await size(),
    };

    row.remove();

    return result;
})()`);

check('preferences start at their defaults',
    preferences?.defaults?.scale === 1
    && preferences?.defaults?.density === 1.5
    && preferences?.defaults?.motion === false
    && preferences?.defaults?.cssScale === '1'
    && preferences?.defaults?.cssGap === '1.5rem'
    && preferences?.defaults?.motionClass === false,
    JSON.stringify(preferences?.defaults));

check('the text-size slider repaints the transcript',
    preferences?.applied?.large !== preferences?.defaults?.fontSize
    && parseFloat(preferences?.applied?.large) > parseFloat(preferences?.defaults?.fontSize),
    JSON.stringify({ default: preferences?.defaults?.fontSize, large: preferences?.applied?.large }));

check('preferences reach the stylesheet',
    preferences?.applied?.cssScale === '1.25'
    && preferences?.applied?.cssGap === '3rem'
    && preferences?.applied?.motionClass === true,
    JSON.stringify(preferences?.applied));

check('preferences are clamped to a usable range',
    preferences?.clamped?.scale === 0.9 && preferences?.clamped?.density === 0.5,
    JSON.stringify(preferences?.clamped));

check('preferences are written to storage',
    preferences?.stored?.scale === '1.25'
    && preferences?.stored?.density === '3'
    && preferences?.stored?.motion === '1',
    JSON.stringify(preferences?.stored));

check('a corrupt stored preference falls back to the default',
    preferences?.corrupt?.scale === 1
    && preferences?.corrupt?.density === 1.5
    && preferences?.corrupt?.cssScale === '1',
    JSON.stringify(preferences?.corrupt));

check('the text-size slider spans a usable range in both directions',
    parseFloat(preferences?.sizes?.small) < parseFloat(preferences?.defaults?.fontSize)
    && parseFloat(preferences?.defaults?.fontSize) < parseFloat(preferences?.applied?.large),
    JSON.stringify({
        small: preferences?.sizes?.small,
        normal: preferences?.defaults?.fontSize,
        large: preferences?.applied?.large,
    }));

check('resetting restores the default reading size',
    preferences?.reset?.scale === 1
    && preferences?.reset?.density === 1.5
    && preferences?.reset?.motion === false
    && preferences?.reset?.cssScale === '1'
    && preferences?.reset?.cssGap === '1.5rem'
    && preferences?.reset?.motionClass === false
    && preferences?.reset?.normal === preferences?.defaults?.fontSize,
    JSON.stringify(preferences?.reset));

// --- Failure notices ------------------------------------------------------
// Every one of these used to fail quietly: the request was fired, the response
// was never inspected, and the UI simply did not change. What is being asserted
// here is that the page now says so out loud.
const notices = await evaluate(`(async () => {
    const data = Alpine.$data(document.querySelector('[x-data]'));

    await data.openConversation({ id: 999999 });
    const openNotice = data.notice;

    // Measured while a notice is showing, because an element that exists but is
    // always display:none would satisfy every other assertion here.
    const el = document.querySelector('[role="alert"]');
    const rect = el?.getBoundingClientRect();

    data.dismissNotice();
    const cleared = data.notice === null;

    await data.updateConversation(999999, { pinned: true });
    const saveNotice = data.notice;

    data.dismissNotice();

    return {
        rendered: el !== null,
        visible: rect !== undefined && rect.height > 0,
        role: el?.getAttribute('role') ?? null,
        liveRegion: document.querySelector('[aria-live="polite"]') !== null,
        openNotice,
        cleared,
        saveNotice,
        idle: data.notice,
    };
})()`);

check('a conversation that will not open says so', notices?.openNotice === 'Could not open that conversation.',
    JSON.stringify(notices?.openNotice));

check('a rejected change reports instead of reloading quietly',
    notices?.saveNotice === 'That change could not be saved.',
    JSON.stringify(notices?.saveNotice));

check('the notice can be dismissed and starts cleared',
    notices?.cleared === true && notices?.idle === null,
    JSON.stringify({ cleared: notices?.cleared, idle: notices?.idle }));

check('failures reach an alert region and status changes a live region',
    notices?.rendered === true
    && notices?.visible === true
    && notices?.role === 'alert'
    && notices?.liveRegion === true,
    JSON.stringify({ role: notices?.role, live: notices?.liveRegion, visible: notices?.visible }));

// --- Command palette ----------------------------------------------------
const palette = await evaluate(`(async () => {
    const data = Alpine.$data(document.querySelector('[x-data]'));
    const wait = (ms) => new Promise((resolve) => setTimeout(resolve, ms));

    data.$refs.input.focus();
    document.dispatchEvent(new KeyboardEvent('keydown', { key: 'k', ctrlKey: true, bubbles: true }));
    await Alpine.nextTick();
    await wait(120);

    const result = {
        openedByShortcut: data.palette.open,
        focused: document.activeElement?.id,
        commandCount: data.paletteItems().filter((i) => i.kind === 'command').length,
        labelled: document.querySelector('[role="dialog"][aria-label="Command palette"]') !== null,
        combobox: document.getElementById('whale-palette-input')?.getAttribute('role') ?? null,
    };

    // Chats are ranked above commands, because "the conversation about X" is
    // what a shortcut like this is usually reached for. A fresh workspace
    // legitimately has no history — history is scoped per workspace — so one is
    // created here rather than skipping the check on a clean database.
    let conversation = data.conversations[0];

    if (!conversation) {
        // Titled so the word also appears in a command's label, which is what
        // lets the ranking below compare the two groups instead of one of them
        // being absent (findIndex returns -1 for a group with no match, and
        // 0 < -1 is false for the wrong reason).
        const created = await fetch(data.routes.history, {
            method: 'POST',
            headers: { ...data.headers(), 'Content-Type': 'application/json' },
            body: JSON.stringify({ title: 'Grid notes' }),
        });

        if (created.ok) {
            await data.loadHistory();
            conversation = data.conversations[0];
            result.seeded = true;
        }
    }

    if (conversation) {
        data.palette.query = 'grid';
        await Alpine.nextTick();
        const items = data.paletteItems();
        const chatAt = items.findIndex((i) => i.kind === 'chat');
        const commandAt = items.findIndex((i) => i.kind === 'command');

        result.threadCount = items.filter((i) => i.kind === 'chat').length;
        result.commandCountInQuery = items.filter((i) => i.kind === 'command').length;
        result.threadFirst = items[0]?.kind === 'chat';
        result.threadRankedFirst = chatAt === 0 && commandAt > chatAt;
    }

    data.palette.query = '';
    await Alpine.nextTick();
    const total = data.paletteItems().length;

    data.movePalette(1);
    result.movedDown = data.palette.index === 1;
    data.movePalette(-1);
    result.movedBackUp = data.palette.index === 0;
    data.movePalette(-1);
    result.wrapped = data.palette.index === total - 1;

    // A query with no matches must not wrap onto some unrelated row.
    data.palette.query = 'zzz-no-such-command';
    await Alpine.nextTick();
    result.emptyCount = data.paletteItems().length;
    data.movePalette(1);
    data.runPalette();
    await Alpine.nextTick();
    result.staysOpenOnEmpty = data.palette.open === true;

    data.palette.query = 'layout';
    await Alpine.nextTick();
    const before = data.historyLayout;
    data.runPalette();
    await Alpine.nextTick();
    result.ranCommand = {
        before,
        after: data.historyLayout,
        dismissed: data.palette.open === false,
    };

    return result;
})()`);

check('Ctrl+K opens the palette and focuses its input',
    palette?.openedByShortcut === true
    && palette?.focused === 'whale-palette-input'
    && palette?.labelled === true
    && palette?.combobox === 'combobox',
    JSON.stringify(palette));

check('the palette offers the page commands', palette?.commandCount >= 6, `commands=${palette?.commandCount}`);

check('the palette searches chats as well as commands',
    palette?.threadFirst === true && palette?.threadRankedFirst === true,
    JSON.stringify({ threadFirst: palette?.threadFirst, rankedFirst: palette?.threadRankedFirst }));

check('arrow keys move the highlight and wrap around',
    palette?.movedDown === true && palette?.movedBackUp === true && palette?.wrapped === true,
    JSON.stringify({ down: palette?.movedDown, up: palette?.movedBackUp, wrap: palette?.wrapped }));

check('an empty palette result is inert',
    palette?.emptyCount === 0 && palette?.staysOpenOnEmpty === true,
    JSON.stringify({ empty: palette?.emptyCount, stayedOpen: palette?.staysOpenOnEmpty }));

check('Enter runs the highlighted command and dismisses the palette',
    palette?.ranCommand?.before !== palette?.ranCommand?.after
    && palette?.ranCommand?.dismissed === true,
    JSON.stringify(palette?.ranCommand));

// --- Modal focus handling -------------------------------------------------
// Both dialogs declare aria-modal="true", which promises that the background is
// inert. These checks hold that promise to account: Tab has to wrap at the edges
// of the dialog instead of walking into the page behind it, and closing has to
// hand focus back to whatever opened it rather than dropping it on the body.
const focusCheck = await evaluate(`(async () => {
    const data = Alpine.$data(document.querySelector('[x-data]'));
    const wait = (ms) => new Promise((resolve) => setTimeout(resolve, ms));
    const focusables = (scope) => [...scope.querySelectorAll(
        'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])',
    )].filter((element) => element.offsetParent !== null);

    const composer = document.querySelector('textarea[x-ref="input"]');

    // The palette test above ran a command, which may have been the one that
    // opens settings — starting from a known state keeps the assertions below
    // about this block rather than about whichever command happened to be first.
    data.settingsOpen = false;
    await Alpine.nextTick();
    await wait(150);

    composer.focus();

    const openedFrom = document.activeElement;
    const result = { palette: {}, settings: {} };

    data.openPalette();
    await Alpine.nextTick();
    await wait(80);

    const palette = document.querySelector('[aria-label="Command palette"]');
    const paletteItems = focusables(palette);

    result.palette.focused = document.activeElement?.id === 'whale-palette-input';
    result.palette.inside = palette.contains(document.activeElement);

    // Focus the last row first, so the Tab below is the one that would
    // otherwise escape the dialog rather than the first, harmless one.
    paletteItems[paletteItems.length - 1]?.focus();
    palette.dispatchEvent(new KeyboardEvent('keydown', { key: 'Tab', bubbles: true }));
    await wait(40);
    result.palette.wrapsForward = document.activeElement === paletteItems[0];

    paletteItems[0]?.focus();
    palette.dispatchEvent(new KeyboardEvent('keydown', { key: 'Tab', shiftKey: true, bubbles: true }));
    await wait(40);
    result.palette.wrapsBackward = document.activeElement === paletteItems[paletteItems.length - 1];

    data.closePalette();
    await Alpine.nextTick();
    await wait(150);
    result.palette.restored = document.activeElement === openedFrom;

    data.settingsOpen = true;
    await Alpine.nextTick();
    await wait(150);

    const settings = document.querySelector('[aria-label="Settings"]');
    const settingsItems = focusables(settings);

    result.settings.inside = settings !== null && settings.contains(document.activeElement);

    settingsItems[settingsItems.length - 1]?.focus();
    settings.dispatchEvent(new KeyboardEvent('keydown', { key: 'Tab', bubbles: true }));
    await wait(40);
    result.settings.wrapsForward = document.activeElement === settingsItems[0];

    data.settingsOpen = false;
    await Alpine.nextTick();
    await wait(300);
    result.settings.restored = document.activeElement === openedFrom;

    return result;
})()`);

check('the command palette keeps Tab inside and returns focus on close',
    focusCheck?.palette?.focused === true
    && focusCheck?.palette?.inside === true
    && focusCheck?.palette?.wrapsForward === true
    && focusCheck?.palette?.wrapsBackward === true
    && focusCheck?.palette?.restored === true,
    JSON.stringify(focusCheck?.palette));

check('the settings dialog keeps Tab inside and returns focus on close',
    focusCheck?.settings?.inside === true
    && focusCheck?.settings?.wrapsForward === true
    && focusCheck?.settings?.restored === true,
    JSON.stringify(focusCheck?.settings));

// --- Image generation styles ----------------------------------------------
// Compared against the capabilities endpoint rather than a hardcoded number:
// the catalog is server data, and a literal count here only ever tells you the
// catalog changed without saying whether the page actually picked it up.
const styles = await evaluate(`(async () => {
    const data = Alpine.$data(document.querySelector('[x-data]'));

    try {
        const payload = await (await fetch('/ai/chat/capabilities', { headers: { Accept: 'application/json' } })).json();

        return {
            count: data.imageStyles.length,
            serverCount: payload.styles?.length,
            first: data.imageStyles[0]?.id,
            ids: data.imageStyles.map((s) => s.id),
            serverIds: (payload.styles ?? []).map((s) => s.id),
            default: data.imageStyle,
        };
    } catch (error) {
        return { __err: String(error) };
    }
})()`);

check('every image style loads',
    styles?.count > 0 && JSON.stringify(styles?.ids) === JSON.stringify(styles?.serverIds),
    JSON.stringify(styles));
check('the "Any" style leads the list', styles?.first === 'any', styles?.first);
check('cartoon, logo, animation and realistic are all offered',
    ['cartoon', 'logo', 'animation', 'realistic'].every((id) => styles?.ids?.includes(id)),
    JSON.stringify(styles?.ids));
check('no style is implied by default', styles?.default === 'any', styles?.default);

const styled = await evaluate(`(async () => {
    const data = Alpine.$data(document.querySelector('[x-data]'));
    data.mode = 'image';
    await Alpine.nextTick();
    const chips = Array.from(document.querySelectorAll('[aria-pressed][title]'))
        .filter((el) => el.textContent.includes('Cartoon'));
    if (chips[0]) chips[0].click();
    await Alpine.nextTick();
    return {
        style: data.imageStyle,
        visible: chips.length > 0,
        mode: data.mode,
    };
})()`);

check('the style chips appear in image mode', styled?.visible === true, JSON.stringify(styled));
check('clicking a chip selects that style', styled?.style === 'cartoon', styled?.style);

// --- Draw and scratch board -----------------------------------------------
const slashArt = await evaluate(`(async () => {
    const data = Alpine.$data(document.querySelector('[x-data]'));
    data.mode = 'chat';
    data.runSlashCommand({ id: 'art' });
    await Alpine.nextTick();
    return { open: data.board.open, mode: data.mode };
})()`);

check('"/art" opens the draw and scratch board', slashArt?.open === true, JSON.stringify(slashArt));
check('"/art" also selects the art assistant mode', slashArt?.mode === 'art', slashArt?.mode);

const draw = await evaluate(`(async () => {
    const data = Alpine.$data(document.querySelector('[x-data]'));
    if (!data.board.open) data.openBoard();
    const canvas = document.querySelector('canvas[x-ref="boardCanvas"]');
    if (!canvas) return { __error: 'canvas missing' };

    await Alpine.nextTick();

    // Wait for the modal to be laid out before sampling pixels.
    let rect = canvas.getBoundingClientRect();
    for (let attempt = 0; attempt < 20 && rect.width < 10; attempt++) {
        await new Promise((r) => setTimeout(r, 50));
        rect = canvas.getBoundingClientRect();
    }

    if (rect.width < 10 || rect.height < 10) {
        const chain = [];
        for (let el = canvas; el; el = el.parentElement) {
            const cs = getComputedStyle(el);
            const r = el.getBoundingClientRect();
            chain.push({
                tag: el.tagName.toLowerCase(),
                cls: (el.getAttribute('class') || '').slice(0, 60),
                display: cs.display,
                h: Math.round(r.height),
                w: Math.round(r.width),
            });
        }
        return { __error: 'canvas has no layout size', rect: { w: rect.width, h: rect.height }, chain };
    }

    const px = (x, y) => {
        const r = canvas.getBoundingClientRect();
        const cx = Math.round((x - r.left) * (canvas.width / r.width));
        const cy = Math.round((y - r.top) * (canvas.height / r.height));
        return Array.from(canvas.getContext('2d').getImageData(cx, cy, 1, 1).data);
    };

    const startX = rect.left + rect.width * 0.3;
    const startY = rect.top + rect.height * 0.5;
    const endX = startX + Math.min(60, rect.width * 0.2);
    const endY = startY + 30;

    const opts = (x, y) => ({ clientX: x, clientY: y, pointerId: 1, bubbles: true, pointerType: 'mouse' });

    const before = px(startX, startY);
    canvas.dispatchEvent(new PointerEvent('pointerdown', opts(startX, startY)));
    canvas.dispatchEvent(new PointerEvent('pointermove', opts(endX, endY)));
    canvas.dispatchEvent(new PointerEvent('pointerup', opts(endX, endY)));
    const after = px(endX, endY);

    const historyAfterStroke = data.board.history.length;

    data.boardUndo();
    await new Promise((r) => setTimeout(r, 150));
    const undone = px(endX, endY);

    const historyBeforeClear = data.board.history.length;
    data.boardClear();
    await new Promise((r) => setTimeout(r, 50));
    const cleared = data.board.history.length;
    const blank = px(startX, startY);

    return { before, after, historyAfterStroke, undone, historyBeforeClear, cleared, blank };
})()`);

if (draw?.__error) {
    problems.push(`board: ${JSON.stringify(draw)}`);
}

const isDark = (rgb) => rgb && rgb[0] < 160 && rgb[3] === 255;
const isWhite = (rgb) => rgb && rgb[0] > 240 && rgb[1] > 240 && rgb[2] > 240;

check('the board starts blank and white', isWhite(draw?.before), JSON.stringify(draw?.before));
check('a pointer stroke actually marks the canvas', isDark(draw?.after), JSON.stringify(draw?.after));
check('each stroke is recorded for undo', draw?.historyAfterStroke >= 1, `history=${draw?.historyAfterStroke}`);
check('undo removes the stroke', isWhite(draw?.undone), JSON.stringify(draw?.undone));
check('clear snapshots then blanks the canvas',
    isWhite(draw?.blank) && draw?.cleared === (draw?.historyBeforeClear ?? 0) + 1,
    JSON.stringify({ blank: draw?.blank, before: draw?.historyBeforeClear, after: draw?.cleared }));

const boardActions = await evaluate(`(async () => {
    const data = Alpine.$data(document.querySelector('[x-data]'));
    data.board.prompt = 'a whale breaching';
    data.closeBoard();
    await Alpine.nextTick();
    const menuItems = Array.from(document.querySelectorAll('button')).map((b) => b.textContent.trim());
    return {
        closed: data.board.open === false,
        hasDrawEntry: menuItems.some((t) => t.includes('Draw or sketch')),
        hasOcr: menuItems.some((t) => t.includes('Scan image (OCR)')),
    };
})()`);

check('the board closes', boardActions?.closed === true, JSON.stringify(boardActions));
check('the attach menu offers the draw board', boardActions?.hasDrawEntry === true, JSON.stringify(boardActions));
check('the attach menu still offers OCR', boardActions?.hasOcr === true);

// --- Workspace IDE -----------------------------------------------------------
// The IDE is a separate page with its own Alpine root, so it gets its own
// target, connection and evaluate helper.
const workspaceUrl = (process.env.APP_URL ?? 'http://127.0.0.1:8000').replace(/\/$/, '') + '/ai/chat/workspace';
const wsTarget = await (await fetch(`${CDP}/json/new?${encodeURIComponent(workspaceUrl)}`, { method: 'PUT' })).json();
const wsSocket = new WebSocket(wsTarget.webSocketDebuggerUrl);
let wsId = 0;
const wsPending = new Map();

wsSocket.onmessage = (event) => {
    const message = JSON.parse(event.data);

    if (message.method === 'Log.entryAdded' && message.params?.entry?.level === 'error') {
        problems.push(`workspace console: ${message.params.entry.text}`);
    }

    if (message.method === 'Runtime.consoleAPICalled' && message.params?.type === 'error') {
        const text = (message.params.args ?? []).map((arg) => arg.value ?? arg.description ?? '').join(' ');
        problems.push(`workspace console: ${text}`);
    }

    if (message.method === 'Runtime.exceptionThrown') {
        const details = message.params.exceptionDetails;
        problems.push(`workspace exception: ${details.exception?.description ?? details.text}`);
    }

    if (message.id && wsPending.has(message.id)) {
        wsPending.get(message.id)(message);
        wsPending.delete(message.id);
    }
};

await new Promise((resolve) => { wsSocket.onopen = resolve; });

const wsSend = (method, params = {}) => new Promise((resolve) => {
    const requestId = ++wsId;
    wsPending.set(requestId, resolve);
    wsSocket.send(JSON.stringify({ id: requestId, method, params }));
});

const wsEvaluate = async (expression) => {
    const response = await wsSend('Runtime.evaluate', { expression, awaitPromise: true, returnByValue: true });

    if (response.result?.exceptionDetails) {
        return { __error: response.result.exceptionDetails.exception?.description ?? 'evaluation failed' };
    }

    return response.result?.result?.value;
};

await wsSend('Runtime.enable');
await wsSend('Log.enable');
await new Promise((resolve) => setTimeout(resolve, 5000));

// Seed a small project through the real endpoints, then reload the tree.
const seeded = await wsEvaluate(`(async () => {
    const app = Alpine.$data(document.querySelector('[x-data]'));
    const headers = { 'Accept': 'application/json', 'Content-Type': 'application/json' };
    const workspace = app.workspaceParam();
    const put = (path, contents) => fetch(app.routes.write, {
        method: 'PUT',
        headers,
        body: JSON.stringify({ path, contents, workspace }),
    });

    await put('index.html', '<!doctype html><title>Seed</title><h1>Hi</h1>');
    await put('src/app.js', 'const one = 1;' + String.fromCharCode(10) + 'const two = 2;');
    await put('docs/readme.md', '# Notes');
    await fetch(app.routes.mkdir, {
        method: 'POST',
        headers,
        body: JSON.stringify({ path: 'empty-folder', workspace }),
    });
    await app.refresh();
    await Alpine.nextTick();

    return {
        files: app.files.length,
        // Computed here rather than in Node: CDP serialises an Alpine reactive
        // array as a plain object {"0": ...}, so an array method called on the
        // returned value here would throw on a value that is genuinely an array
        // in the page.
        directories: app.directories.join(','),
        hasEmptyFolder: app.directories.includes('empty-folder'),
        rows: app.explorerRows.map((row) => row.depth + ':' + row.kind + ':' + row.name),
    };
})()`);

check('the IDE boots into Alpine', seeded && !seeded.__error, JSON.stringify(seeded)?.slice(0, 160));
check('every seeded file is listed', seeded?.files === 3, `files=${seeded?.files}`);
check('directories are reported, including the empty one',
    seeded?.hasEmptyFolder === true,
    `directories=${seeded?.directories}`);
check('the tree starts collapsed to top-level entries',
    JSON.stringify(seeded?.rows) === JSON.stringify(['0:dir:docs', '0:dir:empty-folder', '0:dir:src', '0:file:index.html']),
    JSON.stringify(seeded?.rows));

const expanded = await wsEvaluate(`(async () => {
    const app = Alpine.$data(document.querySelector('[x-data]'));
    app.toggleDir('src');
    app.toggleDir('docs');
    await Alpine.nextTick();
    return { rows: app.explorerRows.map((row) => row.depth + ':' + row.kind + ':' + row.name) };
})()`);

check('expanding folders reveals their contents with depth',
    JSON.stringify(expanded?.rows) === JSON.stringify([
        '0:dir:docs', '1:file:readme.md', '0:dir:empty-folder', '0:dir:src', '1:file:app.js', '0:file:index.html',
    ]),
    JSON.stringify(expanded?.rows));

const filtered = await wsEvaluate(`(async () => {
    const app = Alpine.$data(document.querySelector('[x-data]'));
    app.treeFilter = 'app';
    await Alpine.nextTick();
    const rows = app.explorerRows.map((row) => row.path);
    app.treeFilter = '';
    return { rows };
})()`);

check('the explorer filter narrows the tree',
    JSON.stringify(filtered?.rows) === JSON.stringify(['src', 'src/app.js']), JSON.stringify(filtered?.rows));

// The appended newline goes through fromCharCode: every expression here is a
// template literal, so a written escape would reach the page as a real line
// break and split the string literal in half.
const tabs = await wsEvaluate(`(async () => {
    const app = Alpine.$data(document.querySelector('[x-data]'));
    await app.open('index.html');
    await app.open('src/app.js');
    app.contents = app.contents + String.fromCharCode(10) + 'const three = 3;';
    app.onEdit();
    await Alpine.nextTick();
    return {
        open: app.tabs.map((tab) => tab.path),
        active: app.activePath,
        dirty: app.tabs.map((tab) => tab.path + ':' + tab.dirty),
        hasDirty: app.hasDirty(),
        lines: app.lineNumbers,
    };
})()`);

check('tabs open and stay open',
    JSON.stringify(tabs?.open) === JSON.stringify(['index.html', 'src/app.js']), JSON.stringify(tabs));
check('each tab keeps its own dirty flag',
    JSON.stringify(tabs?.dirty) === JSON.stringify(['index.html:false', 'src/app.js:true']), JSON.stringify(tabs));
check('the unsaved guard sees dirty tabs', tabs?.hasDirty === true, JSON.stringify(tabs));
check('line numbers match the buffer',
    tabs?.lines === '1\n2\n3', JSON.stringify(tabs));

const saved = await wsEvaluate(`(async () => {
    const app = Alpine.$data(document.querySelector('[x-data]'));
    await app.save();
    return { dirty: app.dirty, hasDirty: app.hasDirty(), tab: app.tabFor('src/app.js').dirty };
})()`);

check('saving clears the dirty flag everywhere',
    saved?.dirty === false && saved?.hasDirty === false && saved?.tab === false, JSON.stringify(saved));

const findAndSearch = await wsEvaluate(`(async () => {
    const app = Alpine.$data(document.querySelector('[x-data]'));
    app.findQuery = 'const';
    const matches = app.findMatches.length;
    app.findCounter();

    app.searchQuery = 'Hi';
    await app.runSearch();
    const hits = app.searchResults.map((match) => match.path + ':' + match.line);

    app.searchQuery = 'three';
    await app.runSearch();
    const savedHits = app.searchResults.map((match) => match.path + ':' + match.line);

    return { matches, hits, savedHits };
})()`);

check('find-in-file counts matches in the open buffer', findAndSearch?.matches === 3, `matches=${findAndSearch?.matches}`);
check('search-in-files returns file and line',
    JSON.stringify(findAndSearch?.hits) === JSON.stringify(['index.html:1']),
    JSON.stringify(findAndSearch?.hits));
check('search sees the file just saved from the editor',
    JSON.stringify(findAndSearch?.savedHits) === JSON.stringify(['src/app.js:3']),
    JSON.stringify(findAndSearch?.savedHits));

const layouts = await wsEvaluate(`(async () => {
    const app = Alpine.$data(document.querySelector('[x-data]'));
    app.setLayout('code');
    const code = app.layout;
    app.setLayout('split');
    const split = { layout: app.layout, split: app.split };
    app.split = 65;
    const percent = app.split;
    app.setLayout('preview');
    const preview = app.layout;
    app.setLayout('split');
    return { code, split, percent, preview, splitAgain: app.layout };
})()`);

check('code / split / preview layouts all switch',
    layouts?.code === 'code' && layouts?.split?.layout === 'split'
        && layouts?.preview === 'preview' && layouts?.splitAgain === 'split',
    JSON.stringify(layouts));
check('the split ratio is adjustable', layouts?.split?.split === 50 && layouts?.percent === 65, JSON.stringify(layouts));

const paletteAndMenu = await wsEvaluate(`(async () => {
    const app = Alpine.$data(document.querySelector('[x-data]'));

    app.openPalette('file');
    const fileItems = app.paletteItems().map((item) => item.id);
    app.closePalette();

    app.openPalette('command');
    app.palette.query = 'layout';
    const commands = app.paletteItems().map((item) => item.label);
    app.closePalette();

    const event = {
        clientX: 120,
        clientY: 320,
        preventDefault() {},
        stopPropagation() {},
        target: document.body,
    };
    app.openContextMenu(event, 'src');
    const dirMenu = app.menuItems().map((item) => item.id);
    app.openContextMenu(event, 'src/app.js');
    const fileMenu = app.menuItems().map((item) => item.id);
    app.menu.open = false;

    app.openNewDialog('folder');
    app.dialog.value = 'components';
    await app.submitDialog();
    await app.refresh();
    const created = app.directories.includes('components');
    app.collapseAll();

    return { fileItems, commands, dirMenu, fileMenu, created };
})()`);

check('quick open lists the workspace files',
    JSON.stringify(paletteAndMenu?.fileItems) === JSON.stringify(['docs/readme.md', 'index.html', 'src/app.js']),
    JSON.stringify(paletteAndMenu?.fileItems));
check('the command palette filters commands',
    (paletteAndMenu?.commands ?? []).length > 0, JSON.stringify(paletteAndMenu?.commands));
check('right-click on a folder offers folder actions',
    JSON.stringify(paletteAndMenu?.dirMenu) === JSON.stringify(['new-file', 'new-folder', 'rename', 'delete']),
    JSON.stringify(paletteAndMenu?.dirMenu));
check('right-click on a file offers file actions',
    JSON.stringify(paletteAndMenu?.fileMenu) === JSON.stringify(['open', 'rename', 'delete']),
    JSON.stringify(paletteAndMenu?.fileMenu));
check('the new-folder dialog creates a real directory', paletteAndMenu?.created === true);

// --- Report ----------------------------------------------------------------
const failed = results.filter((r) => !r.pass);

for (const result of results) {
    console.log(`${result.pass ? 'PASS' : 'FAIL'}  ${result.name}${result.detail ? `  [${result.detail}]` : ''}`);
}

if (problems.length) {
    console.log('\nBrowser console problems:');
    for (const problem of problems) console.log(`  ${problem}`);
}

console.log(`\n${results.length - failed.length}/${results.length} passed`);

await send('Browser.close').catch(() => {});
wsSocket.close();
ws.close();

process.exit(failed.length === 0 && problems.length === 0 ? 0 : 1);
