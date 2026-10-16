#!/usr/bin/env bash
set -euo pipefail
enabled=$(python3 -c 'import json; print(str(json.load(open(".github/ci/ci-config.json"))["checks"]["prettier"]).lower())')
if [ "$enabled" != "true" ]; then echo "Prettier: not applicable"; exit 0; fi
set +e
.github/ci/node_modules/.bin/prettier --check core desktop mobile plugin_info README.md 2>&1
status=$?
set -e
if [ "$status" -ne 0 ]; then echo "::warning::Prettier found formatting differences; CI does not rewrite plugin files."; fi
exit 0
