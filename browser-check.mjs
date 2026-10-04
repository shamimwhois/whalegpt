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

// Let the CDN Alpine and the catalog/history fetches settle first.
await new Promise((resolve) => setTimeout(resolve, 6000));

const results = [];
const check = (name, pass, detail = '') => results.push({ name, pass, detail });

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

if (boot) {
    check('all seven assistant modes load', boot.modes === 7, `modes=${boot.modes}`);
    check('three response depths load', boot.depths === 3, `depths=${boot.depths}`);
    check('four thinking efforts load', boot.efforts === 4, `efforts=${boot.efforts}`);
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

// --- Image generation styles ----------------------------------------------
const styles = await evaluate(`(() => {
    const data = Alpine.$data(document.querySelector('[x-data]'));
    return {
        count: data.imageStyles.length,
        first: data.imageStyles[0]?.id,
        ids: data.imageStyles.map((s) => s.id),
        default: data.imageStyle,
    };
})()`);

check('every image style loads', styles?.count === 13, `count=${styles?.count}`);
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
        directories: app.directories,
        rows: app.explorerRows.map((row) => row.depth + ':' + row.kind + ':' + row.name),
    };
})()`);

check('the IDE boots into Alpine', seeded && !seeded.__error, JSON.stringify(seeded)?.slice(0, 160));
check('every seeded file is listed', seeded?.files === 3, `files=${seeded?.files}`);
check('directories are reported, including the empty one',
    (seeded?.directories ?? []).includes('empty-folder'),
    JSON.stringify(seeded?.directories));
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

const tabs = await wsEvaluate(`(async () => {
    const app = Alpine.$data(document.querySelector('[x-data]'));
    await app.open('index.html');
    await app.open('src/app.js');
    app.contents = app.contents + '\nconst three = 3;';
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
    JSON.stringify(tabs?.open) === JSON.stringify(['index.html', 'src/app.js']), JSON.stringify(tabs?.open));
check('each tab keeps its own dirty flag',
    JSON.stringify(tabs?.dirty) === JSON.stringify(['index.html:false', 'src/app.js:true']), JSON.stringify(tabs?.dirty));
check('the unsaved guard sees dirty tabs', tabs?.hasDirty === true);
check('line numbers match the buffer',
    tabs?.lines === '1\n2\n3', JSON.stringify(tabs?.lines));

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
