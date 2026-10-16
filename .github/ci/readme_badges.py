#!/usr/bin/env python3
"""Regenerate the repository badges of a private repo in README.md.

shields.io queries the GitHub API unauthenticated, so every
`img.shields.io/github/*` badge of a private repo renders "repo not found".
Inside GitHub Actions the automatically provided GITHUB_TOKEN *can* read the
private repo, so the workflow resolves the values once and rewrites those badges
as static shields.io badges: no GitHub API call happens when the README is
displayed, so they render for anyone, private repo or not.

Five routes are rewritten in place, so an existing README keeps its layout:

  github/license        -> License <SPDX id>
  github/languages/top  -> Language <top language>
  github/last-commit    -> Last commit <dd/mm/yyyy>
  github/issues         -> Open issues <n>
  github/issues-pr      -> Open pull requests <n>

The script is idempotent: once the routes are gone there is nothing left to
match. Any API problem leaves README.md untouched and exits 0, because a badge
refresh must never turn a repository red.

Environment:
  GITHUB_TOKEN       provided by Actions (needs `contents: read`)
  GITHUB_REPOSITORY  owner/repo, provided by Actions
"""
from __future__ import annotations

import datetime as dt
import json
import os
import re
import sys
import urllib.error
import urllib.request
from pathlib import Path

API = "https://api.github.com"


def api(path: str, token: str) -> tuple[dict, dict]:
    """GET a GitHub API path. A list response arrives under the 'items' key."""
    request = urllib.request.Request(
        f"{API}{path}",
        headers={
            "Authorization": f"Bearer {token}",
            "Accept": "application/vnd.github+json",
            "X-GitHub-Api-Version": "2022-11-28",
            "User-Agent": "jeedom-plugin-readme-badges",
        },
    )
    try:
        with urllib.request.urlopen(request, timeout=30) as response:
            parsed = json.loads(response.read().decode("utf-8"))
            payload = parsed if isinstance(parsed, dict) else {"items": parsed}
            return payload, dict(response.headers)
    except urllib.error.HTTPError as error:
        return {"error": error.code}, dict(error.headers or {})
    except (urllib.error.URLError, TimeoutError, ValueError) as error:
        return {"error": str(error)}, {}


def escape(value) -> str:
    """shields.io static badge escaping: '_' -> space, '__' -> literal '_'."""
    return str(value).replace("-", "--").replace("_", "__").replace(" ", "_")


def static_badge(label: str, message: str, color: str, link: str) -> str:
    return (f"[![{label}](https://img.shields.io/badge/{escape(label)}-{escape(message)}-{color}"
            f"?style=flat-square)]({link})")


def count_from_link(header: dict) -> int:
    """Open count, read from the `Link: <...&page=N>; rel="last"` header.

    Asking for one item per page makes the last page number equal the total, so
    a single request gives the count instead of paging through every item.
    """
    link = header.get("Link") or header.get("link") or ""
    match = re.search(r"[?&]page=(\d+)>;\s*rel=\"last\"", link)
    return int(match.group(1)) if match else 0


def build_badges(slug: str, token: str) -> dict[str, str]:
    repo, _ = api(f"/repos/{slug}", token)
    if "error" in repo:
        raise RuntimeError(f"cannot read {slug}: {repo['error']}")

    html = f"https://github.com/{slug}"

    licence = (repo.get("license") or {}).get("spdx_id") or "none"

    languages = api(f"/repos/{slug}/languages", token)[0]
    top_language = "n/a"
    if isinstance(languages, dict) and languages and "error" not in languages:
        top_language = max(languages.items(), key=lambda item: item[1])[0]

    commits = api(f"/repos/{slug}/commits?per_page=1", token)[0].get("items")
    if isinstance(commits, list) and commits:
        raw = commits[0]["commit"]["committer"]["date"]
        when = dt.datetime.fromisoformat(raw.replace("Z", "+00:00"))
        days = (dt.datetime.now(dt.timezone.utc) - when).days
        stamp = when.strftime("%d/%m/%Y")
        commit_color = "brightgreen" if days <= 30 else ("yellow" if days <= 180 else "orange")
    else:
        stamp, commit_color = "n/a", "lightgrey"

    open_issues = count_from_link(api(f"/repos/{slug}/issues?state=open&per_page=1", token)[1])
    open_pulls = count_from_link(api(f"/repos/{slug}/pulls?state=open&per_page=1", token)[1])

    return {
        "github/license": static_badge(
            "License", licence, "blue", f"{html}/blob/HEAD/LICENSE"),
        "github/languages/top": static_badge(
            "Language", top_language, "informational", html),
        "github/last-commit": static_badge(
            "Last commit", stamp, commit_color, f"{html}/commits"),
        "github/issues": static_badge(
            "Open issues", str(open_issues),
            "brightgreen" if open_issues == 0 else "yellow", f"{html}/issues"),
        "github/issues-pr": static_badge(
            "Open pull requests", str(open_pulls),
            "brightgreen" if open_pulls == 0 else "yellow", f"{html}/pulls"),
    }


def rewrite(readme: str, badges: dict[str, str]) -> tuple[str, list[str]]:
    touched = []
    # Longest route first: 'github/issues' is a prefix of 'github/issues-pr', and
    # the trailing guard makes the short route unable to match the longer one.
    for route in sorted(badges, key=len, reverse=True):
        pattern = re.compile(
            r"\[!\[[^\]]*\]\(https://img\.shields\.io/" + re.escape(route) + r"(?![-\w])[^)]*\)\]\([^)]*\)"
        )
        readme, count = pattern.subn(lambda _match, badge=badges[route]: badge, readme)
        if count:
            touched.append(f"{route} x{count}")
    return readme, touched


def main() -> int:
    token = os.environ.get("GITHUB_TOKEN") or os.environ.get("GH_TOKEN")
    slug = os.environ.get("GITHUB_REPOSITORY")
    if not token or not slug:
        print("::warning::GITHUB_TOKEN or GITHUB_REPOSITORY missing; README untouched")
        return 0

    readme_path = Path("README.md")
    if not readme_path.exists():
        print("::warning::README.md missing; nothing to do")
        return 0

    try:
        badges = build_badges(slug, token)
    except (RuntimeError, KeyError, TypeError, ValueError) as error:
        print(f"::warning::{error}; README untouched")
        return 0

    current = readme_path.read_text(encoding="utf-8")
    updated, touched = rewrite(current, badges)
    if updated == current:
        print("README.md already up to date")
        return 0
    readme_path.write_text(updated, encoding="utf-8")
    print(f"README.md updated: " + ", ".join(touched))
    return 0


if __name__ == "__main__":
    sys.exit(main())
