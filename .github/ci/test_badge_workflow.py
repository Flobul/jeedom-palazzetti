#!/usr/bin/env python3
from pathlib import Path
import re
import unittest

ROOT = Path(__file__).resolve().parents[2]
WORKFLOWS = ROOT / ".github" / "workflows"


class BadgeWorkflowTests(unittest.TestCase):
    def test_repository_badges_share_one_job_and_one_commit(self):
        workflow = (WORKFLOWS / "readme-badges.yml").read_text(encoding="utf-8")
        self.assertIn("name: Repository badges", workflow)
        self.assertIn("push:", workflow)
        self.assertIn("schedule:", workflow)
        self.assertIn("workflow_dispatch:", workflow)

        jobs_section = workflow.split("\njobs:\n", 1)[1]
        direct_jobs = re.findall(r"(?m)^  ([a-zA-Z0-9_-]+):\s*$", jobs_section)
        self.assertEqual(direct_jobs, ["badges"])

        self.assertEqual(workflow.count("actions/checkout@v7"), 1)
        self.assertEqual(workflow.count("actions/setup-python@v7"), 1)
        tests = [
            workflow.index("python3 .github/ci/test_market_badges.py"),
            workflow.index("python3 .github/ci/test_readme_badges.py"),
        ]
        generators = [
            workflow.index("python3 .github/ci/market_badges.py"),
            workflow.index("python3 .github/ci/readme_badges.py"),
        ]
        self.assertLess(max(tests), min(generators))
        self.assertEqual(workflow.count("git commit -m"), 1)
        self.assertFalse((WORKFLOWS / "market-badges.yml").exists())


if __name__ == "__main__":
    unittest.main(verbosity=2)
