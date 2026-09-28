import fs from 'node:fs';
import { execFileSync } from 'node:child_process';
import { writeOutput } from './lib.mjs';

const run = (...args) => execFileSync('git', args, { encoding: 'utf8' });
fs.rmSync('.agent-input', { recursive: true, force: true });
run('add', '-N', '--', '.');
const paths = run('diff', '--name-only', '-z', 'HEAD').split('\0').filter(Boolean);
const forbidden = path =>
  path.startsWith('.github/') ||
  path.startsWith('.git/') ||
  path.startsWith('.codex/') ||
  path.startsWith('.agents/') ||
  path.startsWith('vendor/') ||
  path.startsWith('node_modules/') ||
  path.startsWith('lib/vendor/') ||
  path.startsWith('dev-workspace/scripts/php/vendor/') ||
  path.startsWith('.agent-input/') ||
  path === 'AGENTS.md' ||
  /^\.env(?:\.|$)/.test(path);
if (paths.length > 30) throw new Error('Patch changes more than 30 files');
for (const path of paths) {
  if (forbidden(path)) throw new Error(`Patch includes protected path: ${path}`);
  if (fs.existsSync(path) && fs.lstatSync(path).isSymbolicLink()) throw new Error(`Patch includes a symlink: ${path}`);
}
run('diff', '--check', 'HEAD');
const patch = run('diff', '--binary', 'HEAD');
if (Buffer.byteLength(patch) > 1024 * 1024) throw new Error('Patch exceeds 1 MiB');
if (/^GIT binary patch$/m.test(patch) || /^Binary files .* differ$/m.test(patch)) throw new Error('Binary patch rejected');
if (process.env.PATCH_FILE) fs.writeFileSync(process.env.PATCH_FILE, patch);
writeOutput('has_changes', paths.length ? 'true' : 'false');
console.log(`Prepared ${paths.length} changed file(s)`);
