#!/usr/bin/env node
/**
 * Fail when a patch's description differs between `composer.json` and `README.md`.
 *
 * `composer.json` keys each patch under `extra.patches` by its description, and the
 * README lists the same description after the patch file name:
 *
 *     - **`pest-plugin-mutate-<name>.patch`**: <description>
 *
 * The two copies are written by hand, so they drift: three of sixteen had by the time
 * this check was added. A patch listed in one file and not the other also fails.
 *
 * The check runs its own control first. It reads a synthetic pair that differs in one
 * character and must report exactly one mismatch, so a parser that matches nothing
 * (a changed README line format) exits 2 instead of passing as "no differences".
 *
 * Usage:  node scripts/check-patch-descriptions.mjs [composer.json] [README.md]
 * Exit:   0 identical, 1 a difference, 2 the check could not see (control failed, or
 *         no patch entries were read).
 */
import { readFileSync } from 'node:fs';
import { basename } from 'node:path';

const LINE = /^- \*\*`([^`]+\.patch)`\*\*: (.+?)\r?$/;

export function fromComposer(text) {
    const entries = new Map();
    const patches = JSON.parse(text)?.extra?.patches ?? {};
    for (const byDescription of Object.values(patches)) {
        for (const [description, path] of Object.entries(byDescription)) {
            entries.set(basename(path), description);
        }
    }
    return entries;
}

export function fromReadme(text) {
    const entries = new Map();
    for (const line of text.split('\n')) {
        const match = LINE.exec(line);
        if (match) entries.set(match[1], match[2]);
    }
    return entries;
}

export function compare(composer, readme) {
    const problems = [];
    for (const [file, description] of composer) {
        if (!readme.has(file)) problems.push(`${file}: in composer.json, missing from README.md`);
        else if (readme.get(file) !== description) {
            problems.push(`${file}: descriptions differ\n  composer.json: ${description}\n  README.md:     ${readme.get(file)}`);
        }
    }
    for (const file of readme.keys()) {
        if (!composer.has(file)) problems.push(`${file}: in README.md, missing from composer.json`);
    }
    return problems;
}

function control() {
    const composer = JSON.stringify({ extra: { patches: { 'a/b': { 'Same text (temporary)': 'vendor/x/patches/a-one.patch', 'Other text': 'vendor/x/patches/a-two.patch' } } } });
    const readme = '- **`a-one.patch`**: Same text (temporary)\n- **`a-two.patch`**: Other text!\n';
    const problems = compare(fromComposer(composer), fromReadme(readme));
    return problems.length === 1 && problems[0].startsWith('a-two.patch: descriptions differ');
}

if (!control()) {
    console.error('The control failed: the check cannot tell a differing pair from an identical one.');
    process.exit(2);
}

const [composerPath = 'composer.json', readmePath = 'README.md'] = process.argv.slice(2);
const composer = fromComposer(readFileSync(composerPath, 'utf8'));
const readme = fromReadme(readFileSync(readmePath, 'utf8'));

if (composer.size === 0 || readme.size === 0) {
    console.error(`Read ${composer.size} entries from ${composerPath} and ${readme.size} from ${readmePath}; the check saw nothing.`);
    process.exit(2);
}

const problems = compare(composer, readme);
console.log(`checked ${composer.size} patches in ${composerPath} against ${readme.size} in ${readmePath}`);
if (problems.length > 0) {
    console.error(problems.join('\n'));
    process.exit(1);
}
console.log('descriptions identical');
