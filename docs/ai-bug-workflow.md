# AI bug workflow

This repository has three connected agents:

1. **Triage** runs when an issue opens, changes, or receives a human comment. It classifies the report and assigns one classification label. Confirmed bugs also receive exactly one `severity: ...` label. An issue enters the fixing stage only when the triage result says the bug is confirmed and actionable.
2. **Fixing** starts after the triage labels are saved. It makes a focused patch, and a separate job opens a draft PR against `development`. The PR links the issue and receives `agent:fix-pr`.
3. **Testing** starts as a reusable workflow immediately after the PR is created. It also reruns when the PR changes. It runs PHP syntax, Composer validation, and Jest against both the base and candidate revisions, then independently assesses the reported bug. Its result is `agent:verified`, `agent:failed`, or `agent:manual-test` on both the PR and issue.

The workflow files are [agent-issue-pipeline.yml](../.github/workflows/agent-issue-pipeline.yml) and [agent-pr-validation.yml](../.github/workflows/agent-pr-validation.yml). Prompts, output schemas, and controller scripts are in [`.github/agents/`](../.github/agents/).

## State and severity labels

| Triage result | Labels | Next action |
| --- | --- | --- |
| Confirmed and actionable | `confirmed bug`, one severity, `agent:fixing` | Fixing agent starts |
| Confirmed but not yet actionable | `confirmed bug`, one severity | Maintainer supplies direction |
| Missing evidence | `needs info` | Ask reporter specific questions; rerun on reply |
| Intended or outside this plugin | `not a bug` | Maintainer may close |
| Same as an existing report | `duplicate` | Cite existing issue |
| Ambiguous or sensitive | `needs human review` | Maintainer decides |

Severity definitions are in [triage.md](../.github/agents/triage.md). The scripts create missing labels when first used. Existing labels outside this workflow are retained. GitHub Projects can filter by these labels. To show every new issue on a project board, configure that project's built-in auto-add workflow; adding an issue to a specific project requires its project ID and project permissions, which are not stored in this repository.

## Required repository setup

1. Add `OPENAI_API_KEY` as a GitHub Actions repository or organization secret. Use a key with budget limits suitable for unattended issue traffic.
2. Enable GitHub Actions and allow workflows to create pull requests in **Settings → Actions → General**. If organization policy prevents creation with `GITHUB_TOKEN`, provide `AGENT_GITHUB_TOKEN` as a fine-grained token or GitHub App installation credential with repository contents and pull request write access. The workflow falls back to `GITHUB_TOKEN` when that secret is absent.
3. Protect `development` with human review and required checks. The agent only creates draft PRs; it does not merge.
4. Merge these workflow files into the repository's default branch (`development`). Issue and comment event workflows run from the default branch.
5. Open a small known bug issue. Verify label changes, draft PR creation, and an independent validation comment. Then test an incomplete report and an issue edit or reply.

## Outcomes and failure handling

- The triage agent does not auto-fix reports with missing steps, duplicates, nonbugs, or uncertain decisions.
- A second triage run does not start another fix while an issue is marked `agent:fixing`, `agent:pr-open`, or `agent:failed`. Remove `agent:failed` to permit a retry after improving the issue.
- If the fixing agent produces no patch or a protected-file/oversized patch, the issue gets `agent:failed` and an explanation.
- If GitHub refuses PR creation, the pushed branch remains and the issue receives a comment explaining the failure.
- The test workflow runs without repository secrets while executing candidate code. The AI assessment runs later in a separate read-only job.
- Existing Jest failures are compared against the base revision. New failures block verification; old failures are reported as baseline failures.
- PHP syntax and Composer validation cannot establish that WordPress behavior works. For PHP bugs without a runnable regression test or a real WordPress reproduction, the testing agent must report `inconclusive`, displayed as `agent:manual-test`.
- A nonverified verdict makes the validation workflow fail, so it can be required by branch protection.

## Other implementation paths considered

| Approach | Suitable when | Tradeoff |
| --- | --- | --- |
| Current GitHub Actions plus Codex action | Three explicit stages and deterministic handoffs are needed | Requires an OpenAI API secret and GitHub Actions permissions |
| GitHub Agentic Workflows (`gh aw`) | You prefer a Markdown-based workflow with built-in safe outputs | Public preview; compiled lock files and engine setup required |
| GitHub App plus external worker/queue | Many repositories, higher volume, or a custom Projects integration | More infrastructure and credential management |

The current design uses reusable workflow invocation for the first PR validation. It therefore does not depend on a bot-created PR event, which GitHub may hold for approval or suppress depending on its token and settings.
