import fs from 'node:fs';
import { githubApi, issue, positiveInteger, repository } from './lib.mjs';

const prNumber = positiveInteger(process.env.PR_NUMBER, 'PR_NUMBER');
const issueNumber = positiveInteger(process.env.ISSUE_NUMBER, 'ISSUE_NUMBER');
const repo = repository();
const [pr, bug, comments, files] = await Promise.all([
  githubApi('GET', `/repos/${repo}/pulls/${prNumber}`),
  issue(issueNumber),
  githubApi('GET', `/repos/${repo}/issues/${issueNumber}/comments?per_page=50`),
  githubApi('GET', `/repos/${repo}/pulls/${prNumber}/files?per_page=100`)
]);
fs.mkdirSync('.agent-input', { recursive: true });
fs.writeFileSync('.agent-input/review.json', JSON.stringify({
  pr: { number: prNumber, title: pr.title, body: pr.body, base_sha: pr.base.sha, head_sha: pr.head.sha },
  issue: { number: issueNumber, title: bug.title, body: bug.body, comments: comments.map(comment => comment.body) },
  files: files.map(file => ({ path: file.filename, status: file.status, patch: file.patch || null }))
}, null, 2));
