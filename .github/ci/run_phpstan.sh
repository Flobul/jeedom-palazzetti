#!/usr/bin/env bash
set -u
paths=()
while IFS= read -r path; do paths+=("$path"); done < <(
  for path in core desktop mobile plugin_info; do
    [ -e "$path" ] && printf '%s\n' "$path"
  done
)
if [ "${#paths[@]}" -eq 0 ]; then echo "PHPStan: not applicable"; exit 0; fi
set +e
.github/ci/vendor/bin/phpstan analyse -c .github/ci/phpstan.neon --no-progress --error-format=github "${paths[@]}"
status=$?
set -e
if [ "$status" -eq 0 ]; then echo "PHPStan: passed"; exit 0; fi
if [ "$status" -eq 1 ]; then echo "::warning::PHPStan reported migration findings; syntax checks remain blocking."; exit 0; fi
echo "::error::PHPStan could not complete (exit $status)."
exit "$status"
