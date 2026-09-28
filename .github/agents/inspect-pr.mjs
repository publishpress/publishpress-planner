import { githubApi, issue, positiveInteger, repository, writeOutput } from './lib.mjs';

const prNumber = positiveInteger(process.env.PR_NUMBER, 'PR_NUMBER');
const repo = repository();
const pr = await githubApi('GET', `/repos/${repo}/pulls/${prNumber}`);
const match = /^codex\/issue-(\d+)-(\d+)$/.exec(pr.head.ref);
const issueNumber = match ? positiveInteger(match[1], 'linked issue number') : null;
const linkedIssue = issueNumber ? await issue(issueNumber) : null;
const valid = Boolean(
  pr.state === 'open' && pr.base.ref === 'development' && pr.head.repo.full_name === repo &&
  match && pr.body?.includes(`Fixes #${issueNumber}`) &&
  pr.labels.some(label => label.name === 'agent:fix-pr') &&
  linkedIssue?.labels.some(label => label.name === 'confirmed bug')
);
writeOutput('valid', valid ? 'true' : 'false');
if (valid) {
  writeOutput('pr_number', prNumber);
  writeOutput('issue_number', issueNumber);
  writeOutput('head_sha', pr.head.sha);
  console.log(`Valid agent PR #${prNumber} for issue #${issueNumber}`);
} else {
  console.log(`PR #${prNumber} is outside the agent validation scope`);
}
