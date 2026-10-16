#!/usr/bin/env python3
"""Create a readable CI summary and maintain one rolling GitHub issue."""

from __future__ import annotations

import json
import os
import sys
import urllib.error
import urllib.parse
import urllib.request
from dataclasses import dataclass
from typing import Any

ISSUE_TITLE = "[CI] Rapport qualité automatique"
ISSUE_MARKER = "<!-- jeedom-ci-report -->"
BAD_CONCLUSIONS = {"failure", "cancelled", "timed_out", "action_required", "startup_failure"}
BAD_ANNOTATIONS = {"failure", "warning"}


@dataclass(frozen=True)
class Finding:
    job: str
    level: str
    message: str
    path: str = ""
    start_line: int | None = None


def api_request(method: str, url: str, token: str, payload: dict[str, Any] | None = None) -> Any:
    data = json.dumps(payload).encode("utf-8") if payload is not None else None
    request = urllib.request.Request(
        url,
        data=data,
        method=method,
        headers={
            "Accept": "application/vnd.github+json",
            "Authorization": f"Bearer {token}",
            "X-GitHub-Api-Version": "2022-11-28",
            "User-Agent": "jeedom-ci-report",
        },
    )
    with urllib.request.urlopen(request, timeout=30) as response:
        content = response.read()
        return json.loads(content) if content else None


def api_pages(url: str, token: str, collection_key: str | None = None) -> list[dict[str, Any]]:
    items: list[dict[str, Any]] = []
    page = 1
    while True:
        separator = "&" if "?" in url else "?"
        result = api_request("GET", f"{url}{separator}per_page=100&page={page}", token)
        page_items = result.get(collection_key, []) if collection_key and isinstance(result, dict) else result
        if not isinstance(page_items, list):
            return items
        items.extend(page_items)
        if len(page_items) < 100:
            return items
        page += 1


def collect_report(api_url: str, repository: str, run_id: str, sha: str, token: str) -> tuple[list[dict[str, Any]], list[Finding]]:
    jobs_response = api_request("GET", f"{api_url}/repos/{repository}/actions/runs/{run_id}/jobs?per_page=100", token)
    jobs = jobs_response.get("jobs", []) if isinstance(jobs_response, dict) else []
    jobs = [job for job in jobs if str(job.get("name", "")).lower() != "report"]

    findings: list[Finding] = []
    checks = api_pages(f"{api_url}/repos/{repository}/commits/{sha}/check-runs", token, "check_runs")
    run_fragment = f"/actions/runs/{run_id}/"
    for check in checks:
        details_url = str(check.get("details_url", ""))
        name = str(check.get("name", "Check"))
        if run_fragment not in details_url or name.lower() == "report":
            continue
        annotations_url = check.get("output", {}).get("annotations_url")
        if not annotations_url:
            continue
        for annotation in api_pages(str(annotations_url), token):
            level = str(annotation.get("annotation_level", "notice"))
            if level not in BAD_ANNOTATIONS:
                continue
            findings.append(
                Finding(
                    job=name,
                    level=level,
                    message=" ".join(str(annotation.get("message", "")).split()),
                    path=str(annotation.get("path", "")),
                    start_line=annotation.get("start_line"),
                )
            )
    return jobs, findings


def has_findings(jobs: list[dict[str, Any]], findings: list[Finding]) -> bool:
    return any(str(job.get("conclusion", "")) in BAD_CONCLUSIONS for job in jobs) or bool(findings)


def render_report(repository: str, run_id: str, run_url: str, sha: str, jobs: list[dict[str, Any]], findings: list[Finding]) -> str:
    failing = [job for job in jobs if str(job.get("conclusion", "")) in BAD_CONCLUSIONS]
    lines = [
        ISSUE_MARKER,
        "# Rapport CI",
        "",
        f"Dépôt : `{repository}`  ",
        f"Commit : `{sha[:12]}`  ",
        f"Exécution : {run_url}",
        "",
        "## Résumé",
        "",
        "| Contrôle | Résultat |",
        "|---|---|",
    ]
    icons = {"success": "✅", "failure": "❌", "cancelled": "⏹️", "skipped": "⏭️", "neutral": "ℹ️"}
    for job in jobs:
        conclusion = str(job.get("conclusion") or "in_progress")
        icon = icons.get(conclusion, "⚠️")
        job_url = str(job.get("html_url") or run_url)
        lines.append(f"| [{job.get('name', 'Check')}]({job_url}) | {icon} `{conclusion}` |")

    lines.extend(["", f"## Anomalies ({len(findings)})", ""])
    if not findings and not failing:
        lines.append("Aucune erreur ni alerte détectée.")
    else:
        for job in failing:
            lines.append(f"- **{job.get('name', 'Check')}** : job `{job.get('conclusion')}` — {job.get('html_url', run_url)}")
        for finding in findings:
            location = finding.path
            if finding.start_line and location:
                location += f":{finding.start_line}"
            prefix = f" `{location}`" if location else ""
            lines.append(f"- **{finding.level.upper()} — {finding.job}**{prefix} : {finding.message}")

    lines.extend([
        "",
        "_Ce rapport est remplacé automatiquement à chaque exécution complète de la CI sur la branche par défaut._",
    ])
    return "\n".join(lines) + "\n"


def find_open_issue(api_url: str, repository: str, token: str) -> dict[str, Any] | None:
    issues = api_pages(f"{api_url}/repos/{repository}/issues?state=open", token)
    return next((issue for issue in issues if issue.get("title") == ISSUE_TITLE and "pull_request" not in issue), None)


def update_issue(api_url: str, repository: str, token: str, body: str, findings_present: bool, run_url: str) -> None:
    issue = find_open_issue(api_url, repository, token)
    base = f"{api_url}/repos/{repository}/issues"
    if findings_present:
        payload = {"title": ISSUE_TITLE, "body": body}
        if issue:
            api_request("PATCH", f"{base}/{issue['number']}", token, payload)
        else:
            api_request("POST", base, token, payload)
    elif issue:
        api_request("POST", f"{base}/{issue['number']}/comments", token, {"body": f"✅ CI redevenue saine : {run_url}"})
        api_request("PATCH", f"{base}/{issue['number']}", token, {"state": "closed"})


def main() -> int:
    required = ["GITHUB_TOKEN", "GITHUB_REPOSITORY", "GITHUB_RUN_ID", "GITHUB_SHA", "GITHUB_API_URL", "GITHUB_SERVER_URL"]
    missing = [name for name in required if not os.environ.get(name)]
    if missing:
        print(f"Missing environment variables: {', '.join(missing)}", file=sys.stderr)
        return 2

    token = os.environ["GITHUB_TOKEN"]
    repository = os.environ["GITHUB_REPOSITORY"]
    run_id = os.environ["GITHUB_RUN_ID"]
    sha = os.environ["GITHUB_SHA"]
    api_url = os.environ["GITHUB_API_URL"].rstrip("/")
    run_url = f"{os.environ['GITHUB_SERVER_URL'].rstrip('/')}/{repository}/actions/runs/{run_id}"

    try:
        jobs, findings = collect_report(api_url, repository, run_id, sha, token)
        report = render_report(repository, run_id, run_url, sha, jobs, findings)
        summary = os.environ.get("GITHUB_STEP_SUMMARY")
        if summary:
            with open(summary, "a", encoding="utf-8") as handle:
                handle.write(report)
        update_issue(api_url, repository, token, report, has_findings(jobs, findings), run_url)
        print(report)
        return 0
    except (urllib.error.URLError, urllib.error.HTTPError, ValueError, KeyError) as error:
        print(f"::warning::Unable to publish CI report: {error}")
        return 0


if __name__ == "__main__":
    raise SystemExit(main())
