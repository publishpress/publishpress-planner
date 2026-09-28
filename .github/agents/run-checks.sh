#!/usr/bin/env bash
set -u

candidate="${1:?candidate directory required}"
output="${2:?output directory required}"
mkdir -p "$output"
: > "$output/status.tsv"

run_check() {
    local name="$1"
    shift
    "$@" > "$output/$name.log" 2>&1
    local code=$?
    printf '%s\t%s\n' "$name" "$code" >> "$output/status.tsv"
    echo "$name: exit $code"
}

run_check php_syntax bash -c 'find "$1" -type f -name "*.php" ! -path "*/vendor/*" ! -path "*/node_modules/*" -print0 | xargs -0 -r -n 1 php -l' _ "$candidate"
run_check composer_validate bash -c 'cd "$1" && composer validate --no-check-publish' _ "$candidate"
run_check npm_install_base bash -c 'cd "$1" && npm ci --ignore-scripts --no-audit --no-fund' _ automation
run_check npm_install_candidate bash -c 'cd "$1" && npm ci --ignore-scripts --no-audit --no-fund' _ "$candidate"
if grep -q $'^npm_install_base\t0$' "$output/status.tsv" && grep -q $'^npm_install_candidate\t0$' "$output/status.tsv"; then
    run_check jest_base bash -c 'cd "$1" && npm test -- --runInBand --watch=false --json --outputFile="$2"' _ automation "${output}/jest-base.json"
    run_check jest_candidate bash -c 'cd "$1" && npm test -- --runInBand --watch=false --json --outputFile="$2"' _ "$candidate" "${output}/jest-candidate.json"
else
    printf 'jest_base\tskipped\njest_candidate\tskipped\n' >> "$output/status.tsv"
    echo 'npm installation failed, so Jest was skipped' > "$output/jest_base.log"
    echo 'npm installation failed, so Jest was skipped' > "$output/jest_candidate.log"
fi

node automation/.github/agents/summarize-checks.mjs "$output"
