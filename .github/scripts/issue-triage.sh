#!/usr/bin/env bash
# Issue triage bot (Phase 1) — validates issue format, classifies via LLM
# (optional), applies labels, posts a triage comment.
#
# Called by .github/workflows/issue-triage.yml. Exits 0 on every path —
# the workflow never fails the repo's check suite; verdicts are labels.
set -euo pipefail

ISSUE_NUMBER="${ISSUE_NUMBER:?}"
ISSUE_BODY="${ISSUE_BODY:?}"
REPO="${GITHUB_REPOSITORY:?}"
GH_TOKEN_VALUE="${GH_TOKEN_VALUE:?}"
# Optional: enable LLM classification by setting repo secret TRIAGE_AI_API_KEY
# (OpenRouter-compatible endpoint). Without it the bot does format triage only.
AI_API_KEY="${AI_API_KEY:-}"
AI_BASE_URL="${AI_BASE_URL:-https://openrouter.ai/api/v1}"
AI_MODEL="${AI_MODEL:-anthropic/claude-3-haiku}"

gh_cmd() { GH_TOKEN="$GH_TOKEN_VALUE" gh "$@"; }

apply_label() {
  local label="$1"
  # Label may not exist yet; create it, then add. Never fail the run.
  gh_cmd label create "$label" --repo "$REPO" --color 5319e6 --description "triage bot" >/dev/null 2>&1 || true
  gh_cmd issue edit "$ISSUE_NUMBER" --repo "$REPO" --add-label "$label" >/dev/null 2>&1 || true
}

has() { printf '%s' "$ISSUE_BODY" | grep -qi "$1" && return 0 || return 1; }

comment() {
  local msg="$1"
  gh_cmd issue comment "$ISSUE_NUMBER" --repo "$REPO" --body "$msg" >/dev/null
}

# ---------- 1. Format validation ----------
MISSING=()
has '## *Expected Behavior'   || MISSING+=("Expected Behavior")
has '## *Current Behavior'    || MISSING+=("Current Behavior")
has '## *Steps to Reproduce'  || MISSING+=("Steps to Reproduce")
has '## *Your Environment'    || MISSING+=("Your Environment (WP/PHP/plugin versions)")

REPRO_STEPS_OK=0
if has '## *Steps to Reproduce'; then
  # Repro section must contain at least one numbered step with content.
  if printf '%s' "$ISSUE_BODY" | sed -n '/[Ss]teps to [Rr]eproduce/,/^## /p' | grep -qE '^1\.[[:space:]]*[^[:space:]]'; then
    REPRO_STEPS_OK=1
  else
    MISSING+=("numbered steps under 'Steps to Reproduce'")
  fi
fi

if [ ${#MISSING[@]} -gt 0 ]; then
  apply_label "needs info"
  {
    echo "## 🤖 Triage bot: information needed"
    echo ""
    echo "Before maintainers can act on this report, please add the following section(s):"
    for m in "${MISSING[@]}"; do echo "- **$m**"; done
    echo ""
    echo "Re-open the bot with a comment containing \`@bot triage\` after editing."
  } > /tmp/triage-msg.md
  comment "$(cat /tmp/triage-msg.md)"
  echo "verdict=needs-info" >> "$GITHUB_OUTPUT"
  exit 0
fi

apply_label "triaged"

# ---------- 2. LLM classification (optional) ----------
VERDICT_NOTE="Format OK. Automated classification unavailable (no AI key configured)."
if [ -n "$AI_API_KEY" ]; then
  ESC_BODY=$(printf '%s' "$ISSUE_BODY" | head -c 6000 | python3 -c 'import json,sys; print(json.dumps(sys.stdin.read()))')
  CLASSIFICATION=$(curl -sS --max-time 60 "${AI_BASE_URL}/chat/completions" \
    -H "Authorization: Bearer ${AI_API_KEY}" \
    -H "Content-Type: application/json" \
    -d "$(cat <<EOF
{
  "model": "${AI_MODEL}",
  "max_tokens": 200,
  "temperature": 0,
  "messages": [
    {"role": "system", "content": "You are an issue triage classifier for a WordPress plugin repo. Reply ONLY with JSON: {\"type\":\"bug|feature|question\",\"area\":\"<one word: ui|logic|compatibility|notifications|scheduling|workflow|other>\",\"severity\":\"high|medium|low\"}. Severity: high=data loss/crash/security, medium=broken feature, low=cosmetic/noise. Multiple plugin-version mismatches => area=compatibility."},
    {"role": "user", "content": ${ESC_BODY}}
  ]
}
EOF
)" | python3 -c 'import json,sys; print(json.load(sys.stdin)["choices"][0]["message"]["content"])' 2>/dev/null || true)

  JSON_LINE=$(printf '%s' "$CLASSIFICATION" | grep -oE '\{[^}]+\}' | head -1 || true)
  if [ -n "$JSON_LINE" ]; then
    TYPE=$(printf '%s' "$JSON_LINE" | python3 -c 'import json,sys; print(json.load(sys.stdin).get("type","bug"))' 2>/dev/null || echo bug)
    AREA=$(printf '%s' "$JSON_LINE" | python3 -c 'import json,sys; print(json.load(sys.stdin).get("area","other"))' 2>/dev/null || echo other)
    SEVERITY=$(printf '%s' "$JSON_LINE" | python3 -c 'import json,sys; print(json.load(sys.stdin).get("severity","medium"))' 2>/dev/null || echo medium)
    case "$SEVERITY" in high|medium|low) ;; *) SEVERITY=medium ;; esac
    apply_label "severity/$SEVERITY"
    apply_label "area/$AREA"
    [ "$TYPE" = "bug" ] && apply_label "unconfirmed bug"
    VERDICT_NOTE="Classified by AI: **type:** $TYPE · **area:** $AREA · **severity:** $SEVERITY"
  fi
fi

# ---------- 3. Repro capability check (dormant until Cests exist) ----------
REPRO_NOTE="No automated reproduction is available for this issue yet. The bot will gain the ability to confirm bugs as repro test suites are added to the repo."

{
  echo "## 🤖 Triage summary"
  echo ""
  echo "$VERDICT_NOTE"
  echo ""
  echo "$REPRO_NOTE"
  echo ""
  echo "<sub>Automated triage · re-run with a comment: \`@bot triage\`</sub>"
} > /tmp/triage-msg.md
comment "$(cat /tmp/triage-msg.md)"
echo "verdict=triaged" >> "$GITHUB_OUTPUT"
