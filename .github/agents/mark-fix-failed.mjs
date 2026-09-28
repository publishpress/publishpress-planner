import { githubApi, issue, positiveInteger, replaceIssueLabels, repository } from './lib.mjs';

const number = positiveInteger(process.env.ISSUE_NUMBER, 'ISSUE_NUMBER');
const current = await issue(number);
const labels = new Set(current.labels.map(label => label.name));
if (!labels.has('agent:fixing')) process.exit(0);
await replaceIssueLabels(number, ['agent:fixing'], ['agent:failed']);
await githubApi('POST', `/repos/${repository()}/issues/${number}/comments`, {
  body: 'The fixing agent could not produce or publish a safe patch. Please inspect the linked workflow run and decide how to proceed.'
});
