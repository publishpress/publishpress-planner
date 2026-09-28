import fs from 'node:fs';
import path from 'node:path';
import { execFileSync } from 'node:child_process';
import { githubApi, issue, positiveInteger, replaceIssueLabels, repository, writeOutput } from './lib.mjs';

const issueNumber = positiveInteger(process.env.ISSUE_NUMBER, 'ISSUE_NUMBER');
const runId = positiveInteger(process.env.GITHUB_RUN_ID, 'GITHUB_RUN_ID');
const branch = `codex/issue-${issueNumber}-${runId}`;
const current = await issue(issueNumber);
const labels = new Set(current.labels.map(label => label.name));
if (current.state !== 'open' || !labels.has('confirmed bug') || !labels.has('agent:fixing')) {
  throw new Error('Issue is no longer confirmed or assigned to the fixing agent');
}
const patch = fs.readFileSync(process.env.PATCH_FILE, 'utf8');
if (!patch.trim()) throw new Error('No patch to publish');
const git = (...args) => execFileSync('git', args, { encoding: 'utf8', stdio: ['inherit', 'pipe', 'pipe'] }).trim();
const gh = (...args) => execFileSync('gh', args, { encoding: 'utf8', stdio: ['inherit', 'pipe', 'pipe'] }).trim();
git('switch', '-c', branch);
git('apply', '--check', process.env.PATCH_FILE);
git('apply', process.env.PATCH_FILE);
git('diff', '--check');
git('add', '-A');
git('config', 'user.name', 'publishpress-agent[bot]');
git('config', 'user.email', 'publishpress-agent[bot]@users.noreply.github.com');
git('commit', '-m', `Fix issue #${issueNumber}`);
gh('auth', 'setup-git');
git('push', '--set-upstream', 'origin', branch);

const summary = fs.existsSync(process.env.SUMMARY_FILE)
  ? fs.readFileSync(process.env.SUMMARY_FILE, 'utf8').trim().slice(0, 4000)
  : '';
const bodyFile = path.join(process.env.RUNNER_TEMP, 'fix-pr-body.md');
fs.writeFileSync(bodyFile, [
  `Fixes #${issueNumber}`,
  '',
  'Automated fix candidate. Independent validation will run before human review.',
  '',
  '## Fixing agent report',
  summary || 'No summary was produced.'
].join('\n'));
try {
  gh('pr', 'create', '--draft', '--base', 'development', '--head', branch,
    '--title', `Fix #${issueNumber}: ${current.title}`.slice(0, 180), '--body-file', bodyFile);
} catch (error) {
  await githubApi('POST', `/repos/${repository()}/issues/${issueNumber}/comments`, {
    body: `The fixing agent pushed branch \`${branch}\` but could not create a draft PR. Check the workflow run and repository setting that allows GitHub Actions to create pull requests.`
  });
  throw error;
}
const prNumber = positiveInteger(gh('pr', 'view', branch, '--json', 'number', '--jq', '.number'), 'PR number');
await replaceIssueLabels(prNumber, [], ['agent:fix-pr']);
await replaceIssueLabels(issueNumber, ['agent:fixing'], ['agent:pr-open']);
writeOutput('pr_number', prNumber);
console.log(`Created draft PR #${prNumber} from ${branch}`);
