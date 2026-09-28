import fs from 'node:fs';
import path from 'node:path';

const directory = process.argv[2];
const lines = fs.readFileSync(path.join(directory, 'status.tsv'), 'utf8').trim().split('\n');
const checks = Object.fromEntries(lines.map(line => line.split('\t')));
const failures = file => {
  const result = JSON.parse(fs.readFileSync(path.join(directory, file), 'utf8'));
  return new Set(result.testResults.flatMap(suite => {
    const suiteName = suite.name.split('/').slice(-3).join('/');
    const assertions = (suite.assertionResults || [])
      .filter(test => test.status === 'failed')
      .map(test => `${suiteName}::${test.fullName}`);
    if (suite.status === 'failed' && assertions.length === 0) assertions.push(`${suiteName}::suite failed`);
    return assertions;
  }));
};
let baselineFailures = [];
let newFailures = [];
if (fs.existsSync(path.join(directory, 'jest-base.json')) && fs.existsSync(path.join(directory, 'jest-candidate.json'))) {
  const base = failures('jest-base.json');
  const candidate = failures('jest-candidate.json');
  baselineFailures = [...base];
  newFailures = [...candidate].filter(test => !base.has(test));
}
const infrastructureChecks = ['php_syntax', 'composer_validate', 'npm_install_base', 'npm_install_candidate'];
const infrastructureFailed = infrastructureChecks.some(name => checks[name] !== '0');
const jestMissing = !fs.existsSync(path.join(directory, 'jest-base.json')) || !fs.existsSync(path.join(directory, 'jest-candidate.json'));
const status = infrastructureFailed || jestMissing || newFailures.length ? 'failed' : 'passed';
fs.writeFileSync(path.join(directory, 'summary.json'), JSON.stringify({ status, checks, baselineFailures, newFailures }, null, 2));
console.log(`Check status: ${status}`);
