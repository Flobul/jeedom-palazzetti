#!/usr/bin/env bash
set -euo pipefail
# Repository-specific checks migrated from previous workflows.
jq empty core/config/palazzetti.json plugin_info/info.json
! grep -R "\.html(" core/template/dashboard/Palazzetti.html core/template/mobile/Palazzetti.html
! grep -E "(display_value|collectDate|valueDate): ''#[A-Za-z0-9_]+#''|parseFloat\(''#[A-Za-z0-9_]+#''\)|, ''#[A-Za-z0-9_]+_unit#''" core/template/dashboard/Palazzetti.html core/template/mobile/Palazzetti.html
! grep -F '$(' core/template/dashboard/Palazzetti.html core/template/mobile/Palazzetti.html
