#!/usr/bin/env bash
set -euo pipefail
applicable=0
if [ -f composer.lock ]; then applicable=1; composer audit --no-interaction; else echo "Composer audit: not applicable"; fi
if [ -f package-lock.json ]; then applicable=1; npm audit --omit=dev; else echo "npm audit: not applicable"; fi
if [ -f requirements.txt ]; then applicable=1; python -m pip install --disable-pip-version-check --quiet pip-audit==2.10.1; python -m pip_audit -r requirements.txt; else echo "Python dependency audit: not applicable"; fi
pyfiles=()
while IFS= read -r file; do pyfiles+=("$file"); done < <(find core resources -type f -name '*.py' 2>/dev/null || true)
if [ "${#pyfiles[@]}" -gt 0 ]; then applicable=1; python -m py_compile "${pyfiles[@]}"; else echo "Python syntax: not applicable"; fi
shfiles=()
while IFS= read -r file; do shfiles+=("$file"); done < <(find core resources plugin_info -type f -name '*.sh' 2>/dev/null || true)
if [ "${#shfiles[@]}" -gt 0 ]; then
  applicable=1
  for file in "${shfiles[@]}"; do bash -n "$file"; done
  if command -v shellcheck >/dev/null 2>&1; then
    set +e
    shellcheck -S warning "${shfiles[@]}"
    shellcheck_status=$?
    set -e
    if [ "$shellcheck_status" -ne 0 ]; then echo "::warning::ShellCheck reported existing script hardening findings; bash syntax remains blocking."; fi
  else echo "::notice::shellcheck unavailable"; fi
else echo "Shell checks: not applicable"; fi
[ "$applicable" -eq 1 ] || echo "Dependency/component audits: not applicable for this plugin"
