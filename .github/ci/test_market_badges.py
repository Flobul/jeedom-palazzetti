#!/usr/bin/env python3
"""Unit tests for market_badges.py; no network access is required."""
from __future__ import annotations

import importlib.util
import json
import re
import unittest
import urllib.error
from pathlib import Path
from unittest import mock

MODULE_PATH = Path(__file__).with_name("market_badges.py")
SPEC = importlib.util.spec_from_file_location("market_badges", MODULE_PATH)
if SPEC is None or SPEC.loader is None:
    raise RuntimeError(f"Unable to import {MODULE_PATH}")
market_badges = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(market_badges)


class MarketBadgesTests(unittest.TestCase):
    def test_rendered_market_id_is_pinned_in_ci_config(self):
        root = MODULE_PATH.parents[2]
        readme = root.joinpath("README.md").read_text(encoding="utf-8")
        match = re.search(r"market_display&id=(\d+)", readme)
        if match is None:
            self.skipTest("plugin is not published on the Jeedom market")
        config = json.loads(root.joinpath(".github", "ci", "ci-config.json").read_text(encoding="utf-8"))
        self.assertEqual(int(match.group(1)), config.get("market", {}).get("id"))

    def test_workflow_uses_versioned_actions_and_tests_before_write(self):
        workflow = MODULE_PATH.parents[1].joinpath("workflows", "market-badges.yml").read_text(encoding="utf-8")
        self.assertNotIn("workflow_call:", workflow)
        self.assertIn("timeout-minutes:", workflow)
        self.assertIn("push:", workflow)
        self.assertIn("github.event.repository.default_branch", workflow)
        self.assertIn("cron: '17 4 * * 1'", workflow)
        self.assertIn("actions/checkout@v7", workflow)
        self.assertIn("actions/setup-python@v7", workflow)
        self.assertNotRegex(workflow, r"actions/(?:checkout|setup-python)@[0-9a-f]{40}")
        self.assertLess(workflow.index("run: python3 .github/ci/test_market_badges.py"), workflow.index("run: python3 .github/ci/market_badges.py"))
        self.assertIn("[skip ci]", workflow)

    def test_override_is_validated_against_logical_id(self):
        info = {"id": "tasmota", "name": "Tasmota"}
        config = {"market": {"id": 999}}
        with mock.patch.object(market_badges, "plugin_data", return_value={"id": 999, "logicalId": "other"}):
            market_id, reason = market_badges.resolve_market_id(info, config)
        self.assertIsNone(market_id)
        self.assertIn("logicalId mismatch", reason)

    def test_invalid_override_does_not_raise(self):
        market_id, reason = market_badges.resolve_market_id(
            {"id": "tasmota", "name": "Tasmota"},
            {"market": {"id": "not-a-number"}},
        )
        self.assertIsNone(market_id)
        self.assertIn("invalid market.id", reason)

    def test_candidate_api_failure_is_not_reported_as_unpublished(self):
        info = {"id": "tasmota", "name": "Tasmota"}
        cards = [{"id": 4646, "type": "plugin", "name": "Tasmota", "author": "DomoFlo1"}]
        with mock.patch.object(market_badges, "market_cards", return_value=cards), \
                mock.patch.object(market_badges, "plugin_data", side_effect=urllib.error.URLError("offline")):
            market_id, reason = market_badges.resolve_market_id(info, {})
        self.assertIsNone(market_id)
        self.assertIn("market data unavailable", reason)

    def test_plugin_data_rejects_non_object_result(self):
        with mock.patch.object(market_badges, "fetch", return_value='{"state":"ok","result":"bad"}'):
            market_badges.plugin_data.cache_clear()
            with self.assertRaisesRegex(RuntimeError, "invalid result"):
                market_badges.plugin_data(123)

    def test_zero_rating_with_votes_is_not_reported_as_no_review(self):
        lines = market_badges.badges({
            "id": 1,
            "rate": {"rating": 0, "nb": 2},
            "cost": 0,
            "discountCost": 0,
            "nbBuyer": 0,
            "downloaded": 0,
            "nbComment": 0,
        })
        self.assertIn("Note-0%2F5-red", lines[0])

    def test_render_rejects_duplicate_or_reversed_markers(self):
        valid_badge = "[![License](https://img.shields.io/badge/a-b-blue)](LICENSE)"
        malformed = f"# Plugin\n\n{valid_badge}\n\n{market_badges.END}\n{market_badges.START}\n{market_badges.START}\n"
        with self.assertRaisesRegex(RuntimeError, "invalid market badge markers"):
            market_badges.render(malformed, ["badge"])

    def test_render_is_idempotent(self):
        readme = "# Plugin\n\n[![License](https://img.shields.io/badge/a-b-blue)](LICENSE)\n"
        once = market_badges.render(readme, ["badge"])
        twice = market_badges.render(once, ["badge"])
        self.assertEqual(once, twice)


if __name__ == "__main__":
    unittest.main()
