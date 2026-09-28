import fs from 'node:fs';
import { githubApi, issue, positiveInteger, repository } from './lib.mjs';

const number = positiveInteger(process.env.ISSUE_NUMBER, 'ISSUE_NUMBER');
const item = await issue(number);
if (item.pull_request || item.state !== 'open') throw new Error('Expected an open issue');
const comments = await githubApi('GET', `/repos/${repository()}/issues/${number}/comments?per_page=50`);
fs.mkdirSync('.agent-input', { recursive: true });
fs.writeFileSync('.agent-input/issue.json', JSON.stringify({
  number,
  title: item.title,
  body: item.body,
  labels: item.labels.map(label => label.name),
  comments: comments.map(comment => ({ author: comment.user.login, body: comment.body }))
}, null, 2));
