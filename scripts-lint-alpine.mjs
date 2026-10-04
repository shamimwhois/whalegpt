// Temporary linter: finds Alpine attributes whose JS string literals are split
// across lines, which is valid HTML but a syntax error in Alpine.
import { readdirSync, readFileSync, statSync } from 'node:fs';
import { join } from 'node:path';

const root = process.argv[2] ?? 'resources/views';
const files = [];

const walk = (dir) => {
    for (const entry of readdirSync(dir)) {
        const path = join(dir, entry);
        if (statSync(path).isDirectory()) walk(path);
        else if (path.endsWith('.blade.php')) files.push(path);
    }
};

walk(root);

let problems = 0;

for (const file of files) {
    const lines = readFileSync(file, 'utf8').split('\n');

    for (let i = 0; i < lines.length; i++) {
        const open = lines[i].match(/\s([a-zA-Z:.@-]+)="([^"]*)$/);

        if (!open) continue;

        const directive = open[1];
        const isExpression = directive.startsWith(':')
            || directive.startsWith('x-on:')
            || directive.startsWith('x-bind:')
            || /^x-(show|model|if|for)$/.test(directive);

        if (!isExpression) continue;

        let value = open[2];
        let j = i + 1;

        while (j < lines.length && !value.includes('"')) {
            value += '\n' + lines[j];
            j++;
        }

        let inString = null;

        for (const raw of value.split('\n')) {
            for (let k = 0; k < raw.length; k++) {
                const char = raw[k];
                if (char === '\\') { k++; continue; }
                if (inString) { if (char === inString) inString = null; continue; }
                if (char === "'" || char === '`') inString = char;
            }

            if (inString) {
                console.log(`FAIL ${file}:${i + 1}  ${directive}  (string opened with ${inString} spans a line)`);
                problems++;
                inString = null;
            }
        }

        i = j - 1;
    }
}

console.log(problems === 0 ? 'No Alpine expression problems found.' : `${problems} problem(s).`);
process.exit(problems === 0 ? 0 : 1);
