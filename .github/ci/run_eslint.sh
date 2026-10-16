#!/usr/bin/env bash
set -euo pipefail
files=(); while IFS= read -r file; do files+=("$file"); done < <(find core desktop mobile plugin_info -type f -name '*.js' 2>/dev/null | grep -Ev '(^|/)(vendor|node_modules|3rdparty|third_party|tests)/|\.min\.js$' || true)
if [ "${#files[@]}" -eq 0 ]; then echo "ESLint: not applicable"; exit 0; fi
.github/ci/node_modules/.bin/eslint --config .github/ci/eslint.config.mjs "${files[@]}"
