import { githubApi, normalizeVerdict, positiveInteger, readJson, replaceIssueLabels, repository, RESULT_LABELS } from './lib.mjs';

const prNumber = positiveInteger(process.env.PR_NUMBER, 'PR_NUMBER');
const issueNumber = positiveInteger(process.env.ISSUE_NUMBER, 'ISSUE_NUMBER');
const checks = readJson(process.env.CHECKS_FILE);
const report = normalizeVerdict(readJson(process.env.VERDICT_FILE), checks);
const label = {
  verified: 'agent:verified',
  failed: 'agent:failed',
  inconclusive: 'agent:manual-test'
}[report.verdict];
await replaceIssueLabels(prNumber, RESULT_LABELS, [label]);
await replaceIssueLabels(issueNumber, RESULT_LABELS, [label]);
const lines = [
  '<!-- planner-ai-validation -->',
  '## Independent validation',
  `**Verdict:** ${report.verdict}`,
  `**Automated checks:** ${checks.status}`,
  '', report.summary,
  '', `**Before the fix:** ${report.evidence_before || 'No verified baseline reproduction.'}`,
  '', `**After the fix:** ${report.evidence_after || 'No verified post-fix reproduction.'}`,
  '', `**Regression test:** ${report.regression_test || 'No conclusive regression test.'}`
];
if (report.remaining_risks.length) lines.push('', '**Remaining risks:**', ...report.remaining_risks.map(item => `- ${item}`));
await githubApi('POST', `/repos/${repository()}/issues/${prNumber}/comments`, { body: lines.join('\n') });
console.log(`PR #${prNumber} validation verdict: ${report.verdict}`);
if (report.verdict !== 'verified') process.exitCode = 1;
