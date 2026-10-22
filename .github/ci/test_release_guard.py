#!/usr/bin/env python3
from __future__ import annotations

import json
from pathlib import Path
import subprocess
import sys
import tempfile
import unittest

CI_DIR = Path(__file__).resolve().parent
sys.path.insert(0, str(CI_DIR))

import release_guard as guard


class ReleaseGuardTests(unittest.TestCase):
    def setUp(self):
        self.temporary = tempfile.TemporaryDirectory()
        self.root = Path(self.temporary.name)
        (self.root / "plugin_info").mkdir()
        (self.root / "core" / "class").mkdir(parents=True)
        (self.root / "docs").mkdir()
        self.write_version("0.96.0")
        (self.root / "docs" / "CHANGELOG.md").write_text(
            "# Changelog\n\n"
            "## Non publié\n\n- Aucun changement pour le moment.\n\n"
            "## Version 0.96.0 (12/10/2026)\n\n- Version de référence.\n",
            encoding="utf-8",
        )
        subprocess.run(["git", "init", "-q", str(self.root)], check=True)
        subprocess.run(["git", "-C", str(self.root), "config", "user.name", "Test"], check=True)
        subprocess.run(["git", "-C", str(self.root), "config", "user.email", "test@example.test"], check=True)
        subprocess.run(["git", "-C", str(self.root), "add", "."], check=True)
        subprocess.run(["git", "-C", str(self.root), "commit", "-qm", "baseline"], check=True)
        self.baseline_commit = self.git("rev-parse", "HEAD")
        self.baseline = self.root / ".github" / "release-baseline.json"
        self.baseline.parent.mkdir()
        self.baseline.write_text(
            json.dumps(
                {
                    "schema_version": 1,
                    "plugin_id": "beem",
                    "class_file": "core/class/beem.class.php",
                    "reference_version": "0.96.0",
                    "reference_commit": self.baseline_commit,
                }
            ),
            encoding="utf-8",
        )

    def tearDown(self):
        self.temporary.cleanup()

    def git(self, *arguments: str) -> str:
        return subprocess.run(
            ["git", "-C", str(self.root), *arguments],
            check=True,
            text=True,
            capture_output=True,
        ).stdout.strip()

    def write_version(self, version: str, *, php_version: str | None = None, changelog: bool = True):
        (self.root / "plugin_info" / "info.json").write_text(
            json.dumps({"id": "beem", "pluginVersion": version}),
            encoding="utf-8",
        )
        actual_php = version if php_version is None else php_version
        (self.root / "core" / "class" / "beem.class.php").write_text(
            "<?php\nclass beem { public static $_pluginVersion = "
            + repr(actual_php)
            + "; }\n",
            encoding="utf-8",
        )
        if changelog:
            (self.root / "docs" / "CHANGELOG.md").write_text(
                "# Changelog\n\n"
                "## Non publié\n\n- Aucun changement pour le moment.\n\n"
                f"## Version {version} (20/10/2026)\n\n- Nouvelle version testée.\n\n"
                "## Version 0.96.0 (12/10/2026)\n\n- Version de référence.\n",
                encoding="utf-8",
            )

    def commit_version(self, version: str, **kwargs) -> str:
        self.write_version(version, **kwargs)
        subprocess.run(["git", "-C", str(self.root), "add", "."], check=True)
        subprocess.run(["git", "-C", str(self.root), "commit", "-qm", f"version {version}"], check=True)
        return self.git("rev-parse", "HEAD")

    def evaluate(self, **overrides):
        arguments = {
            "root": self.root,
            "baseline_path": self.baseline,
            "target_commit": self.git("rev-parse", "HEAD"),
            "ci_conclusion": "success",
            "tag_commits": {},
            "release_tags": set(),
        }
        arguments.update(overrides)
        return guard.evaluate_release(**arguments)

    def test_initial_reference_version_does_not_create_a_retroactive_release(self):
        plan = self.evaluate()
        self.assertEqual(plan.action, "none")
        self.assertEqual(plan.reason, "reference-version")

    def test_notation_only_correction_is_not_a_new_version(self):
        self.baseline.write_text(
            json.dumps(
                {
                    "schema_version": 1,
                    "plugin_id": "beem",
                    "class_file": "core/class/beem.class.php",
                    "reference_version": "0.96.00",
                    "reference_commit": self.baseline_commit,
                }
            ),
            encoding="utf-8",
        )
        plan = self.evaluate()
        self.assertEqual(plan.action, "none")
        self.assertEqual(plan.reason, "reference-version")

    def test_new_version_produces_release_notes_and_tag_plan(self):
        commit = self.commit_version("0.97.0")
        plan = self.evaluate(target_commit=commit)
        self.assertEqual(plan.action, "create-tag-and-release")
        self.assertEqual(plan.tag, "v0.97.0")
        self.assertIn("Nouvelle version testée", plan.notes)
        self.assertNotIn("Non publié", plan.notes)

    def test_undated_historical_heading_is_not_in_release_notes(self):
        text = (
            "# Changelog\n\n"
            "## Version 0.97.0 (20/10/2026)\n\n- Nouvelle version testée.\n\n"
            "## 0.96.0\n\n- Historique non daté.\n"
        )
        notes = guard._extract_changelog_entry(text, "0.97.0")
        self.assertIn("Nouvelle version testée", notes)
        self.assertNotIn("Historique non daté", notes)

    def test_php_and_json_version_mismatch_blocks_release(self):
        self.commit_version("0.97.0", php_version="1.02.1")
        with self.assertRaisesRegex(guard.ReleaseGuardError, "PHP version"):
            self.evaluate()

    def test_missing_changelog_entry_blocks_release(self):
        self.commit_version("0.97.0", changelog=False)
        with self.assertRaisesRegex(guard.ReleaseGuardError, "changelog"):
            self.evaluate()

    def test_failed_ci_blocks_release(self):
        commit = self.commit_version("0.97.0")
        with self.assertRaisesRegex(guard.ReleaseGuardError, "CI"):
            self.evaluate(target_commit=commit, ci_conclusion="failure")

    def test_existing_draft_or_prerelease_blocks_stable_release(self):
        commit = self.commit_version("0.97.0")
        with self.assertRaisesRegex(guard.ReleaseGuardError, "draft or prerelease"):
            self.evaluate(target_commit=commit, nonstable_release_tags={"v0.97.0"})

    def test_existing_release_is_idempotent(self):
        commit = self.commit_version("0.97.0")
        plan = self.evaluate(
            target_commit=commit,
            tag_commits={"v0.97.0": commit},
            release_tags={"v0.97.0"},
        )
        self.assertEqual(plan.action, "none")
        self.assertEqual(plan.reason, "release-exists")

    def test_existing_tag_at_validated_commit_recovers_release_creation_failure(self):
        commit = self.commit_version("0.97.0")
        plan = self.evaluate(target_commit=commit, tag_commits={"v0.97.0": commit})
        self.assertEqual(plan.action, "create-release")

    def test_existing_tag_at_another_commit_is_never_moved(self):
        commit = self.commit_version("0.97.0")
        with self.assertRaisesRegex(guard.ReleaseGuardError, "different commit"):
            self.evaluate(target_commit=commit, tag_commits={"v0.97.0": "a" * 40})

    def test_existing_semantically_equivalent_release_prevents_notation_only_release(self):
        commit = self.commit_version("0.97.00")
        plan = self.evaluate(
            target_commit=commit,
            tag_commits={"v0.97.0": "b" * 40},
            release_tags={"v0.97.0"},
        )
        self.assertEqual(plan.action, "none")
        self.assertEqual(plan.reason, "equivalent-release-exists")

    def test_version_must_progress_beyond_latest_stable_release(self):
        commit = self.commit_version("0.97.0")
        with self.assertRaisesRegex(guard.ReleaseGuardError, "newer than latest"):
            self.evaluate(
                target_commit=commit,
                tag_commits={"v0.98.0": "b" * 40},
                release_tags={"v0.98.0"},
            )


if __name__ == "__main__":
    unittest.main(verbosity=2)
