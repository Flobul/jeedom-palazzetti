#!/usr/bin/env python3
from pathlib import Path
import json
import re
import subprocess
import unittest

ROOT = Path(__file__).resolve().parents[2]
WORKFLOW = ROOT / ".github" / "workflows" / "release.yml"


class ReleaseWorkflowTests(unittest.TestCase):
    def test_release_workflow_is_gated_and_recoverable(self):
        text = WORKFLOW.read_text(encoding="utf-8")
        self.assertIn("workflow_run:", text)
        self.assertIn("workflow_dispatch:", text)
        self.assertIn("default: Beta", text)
        self.assertIn("REQUESTED_REF: ${{ inputs.ref || 'Beta' }}", text)
        self.assertNotIn("default: main", text)
        self.assertNotIn("default: master", text)
        self.assertIn("Release automation simulation", text)
        self.assertIn("RELEASE_AUTOMATION_ENABLED: ${{ vars.RELEASE_AUTOMATION_ENABLED", text)
        self.assertIn('[ "$RELEASE_AUTOMATION_ENABLED" = "true" ]', text)
        self.assertIn("Candidate is not the current default-branch commit", text)
        self.assertIn("conclusion == 'success'", text)
        self.assertIn('actions/runs?per_page=100', text)
        self.assertIn('.path == ".github/workflows/ci.yml"', text)
        self.assertNotIn('gh run list --repo "$GITHUB_REPOSITORY" --workflow ci.yml', text)
        self.assertIn("fetch-tags: true", text)
        self.assertIn("release_guard.py", text)
        self.assertIn("create-tag-and-release", text)
        self.assertIn("needs.plan.outputs.action != 'none'", text)
        guard_text = (ROOT / ".github" / "ci" / "release_guard.py").read_text(encoding="utf-8")
        self.assertIn('"create-release"', guard_text)
        self.assertIn("git show-ref --verify", text)
        self.assertIn(".object.type", text)
        self.assertIn("git/tags/$remote_sha", text)
        self.assertNotIn("git tag -f", text)
        self.assertNotIn("git push --force", text)
        self.assertIn("CENTRAL_DISPATCH_ENABLED", text)
        self.assertIn("CENTRAL_DISPATCH_TOKEN", text)
        self.assertIn("repos/Flobul/.jeedom/dispatches", text)
        self.assertIn('"folder": "jeedom-palazzetti"', text)
        self.assertIn("actions/upload-artifact@v6", text)
        self.assertNotIn("pull_request_target", text)

    def test_release_baseline_matches_reference_2_1_1_commit(self):
        baseline = json.loads((ROOT / ".github" / "release-baseline.json").read_text(encoding="utf-8"))
        self.assertEqual(baseline["plugin_id"], "Palazzetti")
        self.assertEqual(baseline["class_file"], "core/class/Palazzetti.class.php")
        self.assertEqual(baseline["reference_version"], "2.1.1")
        self.assertEqual(
            baseline["reference_commit"],
            "904939c6dca410c6f95bb012e041e3d7536af261",
        )
        commit = subprocess.run(
            ["git", "-C", str(ROOT), "cat-file", "-t", baseline["reference_commit"]],
            check=True,
            text=True,
            capture_output=True,
        ).stdout.strip()
        self.assertEqual(commit, "commit")
        info = subprocess.run(
            ["git", "-C", str(ROOT), "show", f'{baseline["reference_commit"]}:plugin_info/info.json'],
            check=True,
            text=True,
            capture_output=True,
        ).stdout
        self.assertEqual(json.loads(info)["pluginVersion"], "2.1.1")

    def test_wordpress_archive_is_preserved_and_not_release_parseable(self):
        archive = (ROOT / "docs" / "CHANGELOG_ARCHIVE_WORDPRESS.md").read_text(encoding="utf-8")
        self.assertEqual(sum(1 for line in archive.splitlines() if re.match(r"^-\s+\S", line)), 71)
        self.assertEqual(len(re.findall(r"!\[[^]]*\]\([^)]*\)", archive)), 1)
        self.assertEqual(archive.count("## 1.2 ("), 2)
        self.assertIn("## ~2.0 (28/21/2021) Fait par @Flobul~", archive)
        structured = re.compile(
            r"^## Version [0-9]+(?:[.-][0-9]+)* \(\d{2}/\d{2}/\d{4}\)\s*$",
            re.MULTILINE,
        )
        self.assertIsNone(structured.search(archive))

    def test_release_guard_is_part_of_blocking_ci(self):
        text = (ROOT / ".github" / "ci" / "extra-checks.sh").read_text(encoding="utf-8")
        self.assertIn("test_release_guard.py", text)
        workflow = (ROOT / ".github" / "workflows" / "check-jeedom.yml").read_text(encoding="utf-8")
        self.assertIn("fetch-depth: 0", workflow)

    def test_central_dispatch_targets_current_repository(self):
        text = WORKFLOW.read_text(encoding="utf-8")
        config = json.loads((ROOT / ".github" / "ci" / "ci-config.json").read_text())
        expected = config["plugin"]["repository"]
        self.assertIn(f'"folder": "{expected}"', text)


if __name__ == "__main__":
    unittest.main(verbosity=2)
