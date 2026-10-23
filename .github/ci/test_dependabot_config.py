#!/usr/bin/env python3
from pathlib import Path
import re
import unittest

ROOT = Path(__file__).resolve().parents[2]
CONFIG = ROOT / ".github" / "dependabot.yml"


class DependabotConfigTests(unittest.TestCase):
    def test_managed_ecosystems_point_to_real_manifests(self):
        text = CONFIG.read_text(encoding="utf-8")
        entries = re.findall(
            r"package-ecosystem:\s*[\"']?([^\s\"']+)[\"']?.*?directory:\s*[\"']?([^\s\"']+)",
            text,
            flags=re.S,
        )
        self.assertTrue(entries)
        self.assertIn(("github-actions", "/"), entries)
        self.assertNotIn(("composer", "/.github/ci"), entries)
        self.assertNotIn(("npm", "/.github/ci"), entries)
        manifests = {"composer": "composer.json", "npm": "package.json"}
        for ecosystem, directory in entries:
            if ecosystem in manifests:
                target = ROOT / directory.lstrip("/") / manifests[ecosystem]
                self.assertTrue(target.is_file(), f"{ecosystem} directory has no {manifests[ecosystem]}: {directory}")
        self.assertNotIn("open-pull-requests-limit: 0", text)


if __name__ == "__main__":
    unittest.main()
