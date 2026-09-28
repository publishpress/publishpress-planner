import fs from 'node:fs';

export const CLASS_LABELS = ['confirmed bug', 'needs info', 'not a bug', 'duplicate', 'needs human review'];
export const SEVERITY_LABELS = ['severity: critical', 'severity: high', 'severity: medium', 'severity: low'];
export const RESULT_LABELS = ['agent:verified', 'agent:failed', 'agent:manual-test'];

export const LABEL_COLORS = {
  'confirmed bug': 'd73a4a',
  'needs info': 'fbca04',
  'not a bug': 'cfd3d7',
  duplicate: 'cfd3d7',
  'needs human review': 'e99695',
  'severity: critical': 'b60205',
  'severity: high': 'd93f0b',
  'severity: medium': 'fbca04',
  'severity: low': '0e8a16',
  'agent:fixing': '1d76db',
  'agent:pr-open': '5319e7',
  'agent:fix-pr': '5319e7',
  'agent:verified': '0e8a16',
  'agent:failed': 'd73a4a',
  'agent:manual-test': 'fbca04'
};

export function positiveInteger(value, name) {
  const number = Number(value);
  if (!Number.isSafeInteger(number) || number < 1) throw new Error(`${name} must be a positive integer`);
  return number;
}

export function repository() {
  const value = process.env.GITHUB_REPOSITORY;
  if (!/^[A-Za-z0-9_.-]+\/[A-Za-z0-9_.-]+$/.test(value || '')) throw new Error('Invalid GITHUB_REPOSITORY');
  return value;
}

export function readJson(path) {
  return JSON.parse(fs.readFileSync(path, 'utf8'));
}

export function writeOutput(name, value) {
  if (process.env.GITHUB_OUTPUT) fs.appendFileSync(process.env.GITHUB_OUTPUT, `${name}=${value}\n`);
}

export async function githubApi(method, path, body) {
  const token = process.env.GH_TOKEN;
  if (!token) throw new Error('GH_TOKEN is required');
  const response = await fetch(`https://api.github.com${path}`, {
    method,
    headers: {
      Accept: 'application/vnd.github+json',
      Authorization: `Bearer ${token}`,
      'X-GitHub-Api-Version': '2022-11-28',
      'User-Agent': 'publishpress-planner-agent-workflows',
      ...(body === undefined ? {} : { 'Content-Type': 'application/json' })
    },
    ...(body === undefined ? {} : { body: JSON.stringify(body) })
  });
  const raw = await response.text();
  const data = raw ? JSON.parse(raw) : null;
  if (!response.ok) {
    const message = data?.message || response.statusText;
    throw new Error(`GitHub API ${method} ${path} failed (${response.status}): ${message}`);
  }
  return data;
}

export async function ensureLabels(names) {
  const repo = repository();
  for (const name of names) {
    if (!(name in LABEL_COLORS)) throw new Error(`Unapproved label: ${name}`);
    try {
      await githubApi('POST', `/repos/${repo}/labels`, {
        name,
        color: LABEL_COLORS[name],
        description: 'Managed by the PublishPress Planner AI bug workflow'
      });
    } catch (error) {
      if (!error.message.includes('(422)')) throw error;
    }
  }
}

export async function issue(number) {
  return githubApi('GET', `/repos/${repository()}/issues/${positiveInteger(number, 'issue number')}`);
}

export async function replaceIssueLabels(number, remove, add) {
  const current = await issue(number);
  const labels = new Set(current.labels.map(label => label.name));
  for (const label of remove) labels.delete(label);
  for (const label of add) labels.add(label);
  await ensureLabels(add);
  await githubApi('PUT', `/repos/${repository()}/issues/${number}/labels`, { labels: [...labels] });
  return current;
}

export function cleanText(value, limit = 4000) {
  if (typeof value !== 'string') throw new Error('Expected text');
  return value.trim().slice(0, limit);
}

export function normalizeTriage(value) {
  const classes = ['confirmed_bug', 'needs_info', 'not_bug', 'duplicate', 'needs_human_review'];
  const severities = ['critical', 'high', 'medium', 'low', 'unknown'];
  if (!classes.includes(value.classification) || !severities.includes(value.severity)) throw new Error('Invalid triage classification or severity');
  if (typeof value.fix_ready !== 'boolean' || !Array.isArray(value.missing_info)) throw new Error('Invalid triage shape');
  if (value.classification === 'confirmed_bug' && value.severity === 'unknown') throw new Error('Confirmed bug requires a severity');
  if (value.classification !== 'confirmed_bug' && (value.severity !== 'unknown' || value.fix_ready)) throw new Error('Non-bug cannot have severity or be fix-ready');
  if (value.classification === 'duplicate') positiveInteger(value.duplicate_of, 'duplicate issue');
  return {
    classification: value.classification,
    severity: value.severity,
    fix_ready: value.fix_ready,
    summary: cleanText(value.summary, 2000),
    evidence: cleanText(value.evidence, 3000),
    missing_info: value.missing_info.slice(0, 8).map(item => cleanText(item, 500)),
    duplicate_of: value.classification === 'duplicate' ? value.duplicate_of : null
  };
}

export function triageLabels(decision) {
  const category = {
    confirmed_bug: 'confirmed bug',
    needs_info: 'needs info',
    not_bug: 'not a bug',
    duplicate: 'duplicate',
    needs_human_review: 'needs human review'
  }[decision.classification];
  return [category, ...(decision.severity === 'unknown' ? [] : [`severity: ${decision.severity}`])];
}

export function normalizeVerdict(value, checks) {
  if (!['verified', 'failed', 'inconclusive'].includes(value.verdict)) throw new Error('Invalid test verdict');
  if (!Array.isArray(value.remaining_risks)) throw new Error('Invalid test report');
  let verdict = value.verdict;
  const before = cleanText(value.evidence_before, 3000);
  const after = cleanText(value.evidence_after, 3000);
  const regression = cleanText(value.regression_test, 1000);
  if (checks.status === 'failed') verdict = 'failed';
  if (verdict === 'verified' && (checks.status !== 'passed' || !before || !after || !regression)) verdict = 'inconclusive';
  return {
    verdict,
    summary: cleanText(value.summary, 3000),
    evidence_before: before,
    evidence_after: after,
    regression_test: regression,
    remaining_risks: value.remaining_risks.slice(0, 8).map(item => cleanText(item, 500))
  };
}
