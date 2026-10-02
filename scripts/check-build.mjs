// Fails when a built script in public/build/assets does not parse.
// One syntax error in app.js stops every script on every page (the sidebar,
// topbar menus and search all stop working), so the deploy runs this right
// after `npm run build` and never uploads a broken bundle.
//
//   npm run build && npm run check:build
import { execFileSync } from 'node:child_process';
import { copyFileSync, mkdtempSync, readdirSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';

const dir = 'public/build/assets';
const files = readdirSync(dir).filter((f) => f.endsWith('.js'));
if (!files.length) {
    console.error(`No built scripts in ${dir}. Run npm run build first.`);
    process.exit(1);
}

const tmp = mkdtempSync(join(tmpdir(), 'check-build-'));
let failed = 0;
for (const file of files) {
    // .mjs: parse as a module, like the browser does with <script type="module">.
    const copy = join(tmp, file.replace(/\.js$/, '.mjs'));
    copyFileSync(join(dir, file), copy);
    try {
        execFileSync(process.execPath, ['--check', copy], { stdio: 'pipe' });
        console.log(`ok      ${file}`);
    } catch (e) {
        failed++;
        const message = String(e.stderr || e.message).split('\n').find((l) => /^\w*Error\b/.test(l)) || 'does not parse';
        console.error(`BROKEN  ${file}: ${message.trim()}`);
    }
}
rmSync(tmp, { recursive: true, force: true });
process.exit(failed ? 1 : 0);
