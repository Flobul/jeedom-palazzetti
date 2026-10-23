#!/usr/bin/env python3
"""Plan a Jeedom plugin Release without creating tags or Releases.

The script is deliberately read-only. GitHub mutations remain visible in the
workflow and are executed only after this guard has produced an approved plan.
"""
from __future__ import annotations

import argparse
from dataclasses import asdict, dataclass
import json
import os
from pathlib import Path
import re
import subprocess
import sys
from typing import Any
from urllib.error import HTTPError, URLError
from urllib.parse import quote
from urllib.request import Request, urlopen

VERSION_RE = re.compile(r"^[0-9]+(?:[.-][0-9]+)*$")
CHANGELOG_HEADING_RE = re.compile(
    r"^## Version (?P<version>[0-9]+(?:[.-][0-9]+)*) \((?P<date>\d{2}/\d{2}/\d{4})\)\s*$",
    re.MULTILINE,
)
PHP_VERSION_RE = re.compile(
    r"public\s+static\s+\$_pluginVersion\s*=\s*(['\"])(?P<version>[^'\"]+)\1\s*;"
)


class ReleaseGuardError(RuntimeError):
    """A release invariant was not satisfied."""


@dataclass(frozen=True)
class ReleasePlan:
    plugin_id: str
    version: str
    semantic_version: str
    tag: str
    target_commit: str
    action: str
    reason: str
    notes: str
    reference_version: str
    reference_commit: str
    latest_release_version: str


def _git(root: Path, *arguments: str) -> str:
    try:
        return subprocess.run(
            ["git", "-C", str(root), *arguments],
            check=True,
            text=True,
            capture_output=True,
        ).stdout.strip()
    except (OSError, subprocess.CalledProcessError) as exc:
        raise ReleaseGuardError("Cannot inspect the Git checkout") from exc


def _read_json(path: Path) -> dict[str, Any]:
    try:
        value = json.loads(path.read_text(encoding="utf-8"))
    except (OSError, json.JSONDecodeError) as exc:
        raise ReleaseGuardError(f"Cannot read JSON file {path}") from exc
    if not isinstance(value, dict):
        raise ReleaseGuardError(f"JSON file must contain an object: {path}")
    return value


def version_key(value: str) -> tuple[int, ...]:
    version = value.strip()
    if version.startswith(("v", "V")):
        version = version[1:]
    if not VERSION_RE.fullmatch(version):
        raise ReleaseGuardError(f"Unsupported stable version: {value!r}")
    parts = [int(part) for part in re.split(r"[.-]", version)]
    while len(parts) > 1 and parts[-1] == 0:
        parts.pop()
    return tuple(parts)


def semantic_version(value: str) -> str:
    return ".".join(str(part) for part in version_key(value))


def _extract_changelog_entry(text: str, version: str) -> str:
    headings = list(CHANGELOG_HEADING_RE.finditer(text))
    matches = [index for index, match in enumerate(headings) if match.group("version") == version]
    if len(matches) != 1:
        raise ReleaseGuardError(
            f"Version {version} must appear exactly once as a dated changelog entry"
        )
    match = headings[matches[0]]
    next_heading = re.search(r"^##\s+", text[match.end():], re.MULTILINE)
    end = match.end() + next_heading.start() if next_heading else len(text)
    section = text[match.end():end].strip()
    items = [line for line in section.splitlines() if re.match(r"^[-*+]\s+\S", line)]
    if not items or all("Aucun changement" in item for item in items):
        raise ReleaseGuardError(f"Version {version} has an incomplete changelog entry")
    return section + "\n"


def _load_versions(root: Path, baseline_path: Path) -> tuple[str, str, str, str, str]:
    info = _read_json(root / "plugin_info" / "info.json")
    baseline = _read_json(baseline_path)
    if baseline.get("schema_version") != 1:
        raise ReleaseGuardError("Unsupported release baseline schema")
    plugin_id = str(info.get("id", "")).strip()
    if plugin_id != str(baseline.get("plugin_id", "")).strip():
        raise ReleaseGuardError("Release baseline plugin id does not match plugin_info/info.json")
    version = str(info.get("pluginVersion", "")).strip()
    version_key(version)
    class_file = root / str(baseline.get("class_file", ""))
    try:
        class_text = class_file.read_text(encoding="utf-8")
    except OSError as exc:
        raise ReleaseGuardError("Cannot read the configured plugin class file") from exc
    matches = [match.group("version").strip() for match in PHP_VERSION_RE.finditer(class_text)]
    if len(matches) != 1:
        raise ReleaseGuardError("The plugin class must declare exactly one public static $_pluginVersion")
    if matches[0] != version:
        raise ReleaseGuardError(
            f"PHP version {matches[0]!r} differs from pluginVersion {version!r}"
        )
    reference_version = str(baseline.get("reference_version", "")).strip()
    version_key(reference_version)
    reference_commit = str(baseline.get("reference_commit", "")).strip().lower()
    if not re.fullmatch(r"[0-9a-f]{40}|[0-9a-f]{64}", reference_commit):
        raise ReleaseGuardError("Release baseline commit is not an immutable Git object id")
    return plugin_id, version, reference_version, reference_commit, str(class_file)


def evaluate_release(
    *,
    root: Path,
    baseline_path: Path,
    target_commit: str,
    ci_conclusion: str,
    tag_commits: dict[str, str],
    release_tags: set[str],
    nonstable_release_tags: set[str] | None = None,
) -> ReleasePlan:
    root = root.resolve()
    target = target_commit.strip().lower()
    if ci_conclusion != "success":
        raise ReleaseGuardError("The blocking CI workflow must conclude successfully")
    head = _git(root, "rev-parse", "HEAD^{commit}").lower()
    if target != head or not re.fullmatch(r"[0-9a-f]{40}|[0-9a-f]{64}", target):
        raise ReleaseGuardError("The validated target commit must equal the checked-out HEAD")

    plugin_id, version, reference_version, reference_commit, _ = _load_versions(
        root, baseline_path
    )
    if _git(root, "cat-file", "-t", reference_commit) != "commit":
        raise ReleaseGuardError("Release baseline commit is missing from the checkout")
    try:
        subprocess.run(
            ["git", "-C", str(root), "merge-base", "--is-ancestor", reference_commit, target],
            check=True,
            capture_output=True,
        )
    except subprocess.CalledProcessError as exc:
        raise ReleaseGuardError("Target commit does not descend from the initialized release baseline") from exc

    current_key = version_key(version)
    reference_key = version_key(reference_version)
    tag = f"v{version}"
    if tag in (nonstable_release_tags or set()):
        raise ReleaseGuardError(
            f"A draft or prerelease already uses {tag}; resolve it explicitly before stable publication"
        )
    changelog_text = (root / "docs" / "CHANGELOG.md").read_text(encoding="utf-8")
    notes = _extract_changelog_entry(changelog_text, version)

    if current_key < reference_key:
        raise ReleaseGuardError("pluginVersion is older than the initialized release reference")
    if current_key == reference_key:
        return ReleasePlan(
            plugin_id,
            version,
            semantic_version(version),
            tag,
            target,
            "none",
            "reference-version",
            notes,
            reference_version,
            reference_commit,
            "",
        )

    stable_versions: list[tuple[tuple[int, ...], str]] = []
    for release_tag in release_tags:
        try:
            stable_versions.append((version_key(release_tag), release_tag.lstrip("vV")))
        except ReleaseGuardError:
            continue
    latest_release_version = max(stable_versions)[1] if stable_versions else ""
    equivalent_release_tags = [
        release_tag
        for key, release_tag in stable_versions
        if key == current_key and f"v{release_tag}" != tag
    ]
    if equivalent_release_tags:
        equivalent_tag = f"v{equivalent_release_tags[0]}"
        if equivalent_tag not in tag_commits:
            raise ReleaseGuardError(
                f"Semantically equivalent Release {equivalent_tag} has no fetched immutable tag"
            )
        return ReleasePlan(
            plugin_id,
            version,
            semantic_version(version),
            tag,
            target,
            "none",
            "equivalent-release-exists",
            notes,
            reference_version,
            reference_commit,
            latest_release_version,
        )
    if stable_versions and current_key < max(item[0] for item in stable_versions):
        raise ReleaseGuardError(
            f"pluginVersion {version} must be newer than latest stable Release {latest_release_version}"
        )

    tag_commit = tag_commits.get(tag, "").lower()
    release_exists = tag in release_tags
    if release_exists:
        if not tag_commit:
            raise ReleaseGuardError(f"GitHub Release {tag} exists without a fetched immutable tag")
        try:
            subprocess.run(
                ["git", "-C", str(root), "merge-base", "--is-ancestor", tag_commit, target],
                check=True,
                capture_output=True,
            )
        except subprocess.CalledProcessError as exc:
            raise ReleaseGuardError(
                f"Existing immutable tag {tag} is not an ancestor of the validated target"
            ) from exc
        action, reason = "none", "release-exists"
    elif tag_commit and tag_commit != target:
        raise ReleaseGuardError(
            f"Existing immutable tag {tag} points to a different commit and must never be moved"
        )
    elif tag_commit:
        action, reason = "create-release", "tag-exists-at-target"
    else:
        action, reason = "create-tag-and-release", "new-stable-version"

    return ReleasePlan(
        plugin_id,
        version,
        semantic_version(version),
        tag,
        target,
        action,
        reason,
        notes,
        reference_version,
        reference_commit,
        latest_release_version,
    )


def local_tag_commits(root: Path) -> dict[str, str]:
    output = _git(root, "for-each-ref", "--format=%(refname:strip=2)", "refs/tags")
    commits: dict[str, str] = {}
    for tag in output.splitlines():
        tag = tag.strip()
        if not tag:
            continue
        commits[tag] = _git(root, "rev-list", "-n", "1", f"refs/tags/{tag}").lower()
    return commits


def github_release_tags(repository: str, token: str) -> tuple[set[str], set[str]]:
    if not re.fullmatch(r"[A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+", repository):
        raise ReleaseGuardError("GITHUB_REPOSITORY is invalid")
    if not token:
        raise ReleaseGuardError("GITHUB_TOKEN is required to inspect existing Releases")
    request = Request(
        f"https://api.github.com/repos/{quote(repository, safe='/')}/releases?per_page=100",
        headers={
            "Accept": "application/vnd.github+json",
            "Authorization": f"Bearer {token}",
            "X-GitHub-Api-Version": "2022-11-28",
            "User-Agent": "jeedom-release-guard/1.0",
        },
    )
    try:
        with urlopen(request, timeout=30) as response:  # nosec B310 - fixed GitHub API host
            payload = json.load(response)
    except HTTPError as exc:
        raise ReleaseGuardError(f"GitHub Releases API returned HTTP {exc.code}") from exc
    except (URLError, json.JSONDecodeError) as exc:
        raise ReleaseGuardError("GitHub Releases API is unavailable or invalid") from exc
    if not isinstance(payload, list):
        raise ReleaseGuardError("GitHub Releases API returned an invalid response")
    stable: set[str] = set()
    nonstable: set[str] = set()
    for item in payload:
        if not isinstance(item, dict):
            continue
        tag = str(item.get("tag_name", "")).strip()
        if not tag:
            continue
        if item.get("draft") is True or item.get("prerelease") is True:
            nonstable.add(tag)
        else:
            stable.add(tag)
    return stable, nonstable


def _write_github_output(path: Path, plan: ReleasePlan) -> None:
    with path.open("a", encoding="utf-8") as handle:
        for key, value in {
            "action": plan.action,
            "reason": plan.reason,
            "plugin_id": plan.plugin_id,
            "version": plan.version,
            "tag": plan.tag,
            "target_commit": plan.target_commit,
        }.items():
            handle.write(f"{key}={value}\n")


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--root", type=Path, default=Path.cwd())
    parser.add_argument("--baseline", type=Path, required=True)
    parser.add_argument("--target-commit", required=True)
    parser.add_argument("--ci-conclusion", required=True)
    parser.add_argument("--report", type=Path, required=True)
    parser.add_argument("--notes-output", type=Path, required=True)
    parser.add_argument("--github-output", type=Path)
    args = parser.parse_args()

    stable_release_tags, nonstable_release_tags = github_release_tags(
        os.environ.get("GITHUB_REPOSITORY", ""),
        os.environ.get("GITHUB_TOKEN", ""),
    )
    plan = evaluate_release(
        root=args.root,
        baseline_path=args.baseline,
        target_commit=args.target_commit,
        ci_conclusion=args.ci_conclusion,
        tag_commits=local_tag_commits(args.root),
        release_tags=stable_release_tags,
        nonstable_release_tags=nonstable_release_tags,
    )
    args.report.parent.mkdir(parents=True, exist_ok=True)
    args.report.write_text(json.dumps(asdict(plan), ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
    args.notes_output.write_text(plan.notes, encoding="utf-8")
    if args.github_output:
        _write_github_output(args.github_output, plan)
    print(json.dumps(asdict(plan), ensure_ascii=False, indent=2))
    return 0


if __name__ == "__main__":
    try:
        sys.exit(main())
    except (ReleaseGuardError, OSError, json.JSONDecodeError) as exc:
        print(f"ERROR: {exc}", file=sys.stderr)
        sys.exit(1)
