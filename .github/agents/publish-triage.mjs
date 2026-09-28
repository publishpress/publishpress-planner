import { CLASS_LABELS, SEVERITY_LABELS, githubApi, issue, normalizeTriage, positiveInteger, readJson, replaceIssueLabels, repository, triageLabels, writeOutput } from './lib.mjs';

const number = positiveInteger(process.env.ISSUE_NUMBER, 'ISSUE_NUMBER');
const decision = normalizeTriage(readJson(process.env.DECISION_FILE));
const current = await issue(number);
if (current.state !== 'open' || current.pull_request) throw new Error('Issue is no longer open');
const oldLabels = new Set(current.labels.map(label => label.name));
const alreadyRunning = oldLabels.has('agent:fixing') || oldLabels.has('agent:pr-open') || oldLabels.has('agent:failed');
const shouldFix = decision.fix_ready && !alreadyRunning;
const add = triageLabels(decision);
if (shouldFix) add.push('agent:fixing');
await replaceIssueLabels(number, [
  'unconfirmed bug', ...CLASS_LABELS, ...SEVERITY_LABELS,
  ...(decision.classification === 'confirmed_bug' ? [] : ['agent:fixing'])
], add);

const lines = [
  '<!-- planner-ai-triage -->',
  '## AI bug triage',
  `**Result:** ${decision.classification.replaceAll('_', ' ')}`,
  `**Severity:** ${decision.severity}`,
  `**Ready for a fix:** ${decision.fix_ready ? 'yes' : 'no'}`,
  '', decision.summary, '',
  `**Evidence:** ${decision.evidence || 'No conclusive evidence available.'}`
];
if (decision.duplicate_of) lines.push('', `Possible duplicate: #${decision.duplicate_of}`);
if (decision.missing_info.length) lines.push('', '**Needed details:**', ...decision.missing_info.map(item => `- ${item}`));
await githubApi('POST', `/repos/${repository()}/issues/${number}/comments`, { body: lines.join('\n') });
writeOutput('should_fix', shouldFix ? 'true' : 'false');
writeOutput('issue_number', number);
console.log(`Issue #${number}: ${decision.classification}; fix handoff: ${shouldFix}`);
