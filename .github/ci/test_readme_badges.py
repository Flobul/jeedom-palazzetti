#!/usr/bin/env python3
"""Unit tests for readme_badges.py; no network access is required."""
from __future__ import annotations

import importlib.util
import unittest
from pathlib import Path
from unittest import mock

MODULE_PATH = Path(__file__).with_name("readme_badges.py")
SPEC = importlib.util.spec_from_file_location("readme_badges", MODULE_PATH)
if SPEC is None or SPEC.loader is None:
    raise RuntimeError(f"Unable to import {MODULE_PATH}")
readme_badges = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(readme_badges)

FIVE_BADGES = """# Plugin

[![License](https://img.shields.io/github/license/Flobul/jeedom-arenti?style=flat-square)](LICENSE)
[![Language](https://img.shields.io/github/languages/top/Flobul/jeedom-arenti?style=flat-square)](https://github.com/Flobul/jeedom-arenti)
[![Last commit](https://img.shields.io/github/last-commit/Flobul/jeedom-arenti?style=flat-square)](https://github.com/Flobul/jeedom-arenti/commits)
[![Open issues](https://img.shields.io/github/issues/Flobul/jeedom-arenti?style=flat-square)](https://github.com/Flobul/jeedom-arenti/issues)
[![Open pull requests](https://img.shields.io/github/issues-pr/Flobul/jeedom-arenti?style=flat-square)](https://github.com/Flobul/jeedom-arenti/pulls)
"""

BADGES = {
    "github/license": "[![License](https://img.shields.io/badge/License-GPL--2.0-blue?style=flat-square)](https://github.com/Flobul/jeedom-arenti/blob/HEAD/LICENSE)",
    "github/languages/top": "[![Language](https://img.shields.io/badge/Language-PHP-informational?style=flat-square)](https://github.com/Flobul/jeedom-arenti)",
    "github/last-commit": "[![Last commit](https://img.shields.io/badge/Last_commit-11/10/2026-brightgreen?style=flat-square)](https://github.com/Flobul/jeedom-arenti/commits)",
    "github/issues": "[![Open issues](https://img.shields.io/badge/Open_issues-0-brightgreen?style=flat-square)](https://github.com/Flobul/jeedom-arenti/issues)",
    "github/issues-pr": "[![Open pull requests](https://img.shields.io/badge/Open_pull_requests-0-brightgreen?style=flat-square)](https://github.com/Flobul/jeedom-arenti/pulls)",
}


class ReadmeBadgesTests(unittest.TestCase):
    def test_workflow_uses_versioned_actions_and_tests_before_write(self):
        workflow = MODULE_PATH.parents[1].joinpath("workflows", "readme-badges.yml").read_text(encoding="utf-8")
        self.assertNotIn("workflow_call:", workflow)
        self.assertIn("timeout-minutes:", workflow)
        self.assertIn("push:", workflow)
        self.assertIn("github.event.repository.default_branch", workflow)
        self.assertIn("actions/checkout@v7", workflow)
        self.assertIn("actions/setup-python@v7", workflow)
        self.assertNotRegex(workflow, r"actions/(?:checkout|setup-python)@[0-9a-f]{40}")
        self.assertIn("secrets.GITHUB_TOKEN", workflow)
        self.assertIn("[skip ci]", workflow)
        self.assertLess(
            workflow.index("run: python3 .github/ci/test_readme_badges.py"),
            workflow.index("run: python3 .github/ci/readme_badges.py"),
        )

    def test_all_five_private_badge_routes_are_rewritten(self):
        updated, touched = readme_badges.rewrite(FIVE_BADGES, BADGES)
        self.assertNotIn("img.shields.io/github/", updated)
        self.assertEqual(len(touched), 5)
        for badge in BADGES.values():
            self.assertIn(badge, updated)

    def test_issues_route_does_not_consume_the_pull_request_badge(self):
        """`github/issues` is a prefix of `github/issues-pr`; the guard must hold."""
        updated, _ = readme_badges.rewrite(FIVE_BADGES, BADGES)
        self.assertIn(BADGES["github/issues-pr"], updated)
        self.assertEqual(updated.count(BADGES["github/issues"]), 1)
        self.assertNotIn("Open pull requests](https://img.shields.io/badge/Open_issues", updated)

    def test_rewrite_is_idempotent(self):
        once, _ = readme_badges.rewrite(FIVE_BADGES, BADGES)
        twice, touched = readme_badges.rewrite(once, BADGES)
        self.assertEqual(once, twice)
        self.assertEqual(touched, [])

    def test_rewrite_leaves_other_badges_and_prose_untouched(self):
        readme = FIVE_BADGES + "\nSome prose about the plugin.\n"
        updated, _ = readme_badges.rewrite(readme, BADGES)
        self.assertIn("Some prose about the plugin.", updated)
        self.assertIn("# Plugin", updated)

    def test_badge_escaping_matches_shields_conventions(self):
        self.assertEqual(readme_badges.escape("Last commit"), "Last_commit")
        self.assertEqual(readme_badges.escape("GPL-2.0"), "GPL--2.0")
        self.assertEqual(readme_badges.escape("a_b"), "a__b")

    def test_open_count_comes_from_the_last_page_link(self):
        header = {"Link": '<https://api.github.com/x?page=2>; rel="next", '
                          '<https://api.github.com/x?page=7>; rel="last"'}
        self.assertEqual(readme_badges.count_from_link(header), 7)
        self.assertEqual(readme_badges.count_from_link({}), 0)

    def test_unknown_license_is_reported_not_crashed(self):
        with mock.patch.object(readme_badges, "api") as api:
            api.side_effect = [
                ({"private": True, "license": None}, {}),
                ({"PHP": 10}, {}),
                ({"items": []}, {}),
                ({}, {"Link": ""}),
                ({}, {"Link": ""}),
            ]
            badges = readme_badges.build_badges("Flobul/x", "token")
        self.assertIn("License-none-blue", badges["github/license"])
        self.assertIn("Last_commit-n/a", badges["github/last-commit"])

    def test_api_failure_leaves_readme_untouched(self):
        with mock.patch.object(readme_badges, "api", return_value=({"error": 404}, {})):
            with self.assertRaises(RuntimeError):
                readme_badges.build_badges("Flobul/missing", "token")


if __name__ == "__main__":
    unittest.main()
