import importlib.util
import sys
import unittest
from pathlib import Path

SCRIPT = Path(__file__).resolve().parent / "report_ci.py"
spec = importlib.util.spec_from_file_location("report_ci", SCRIPT)
assert spec is not None and spec.loader is not None
report_ci = importlib.util.module_from_spec(spec)
sys.modules[spec.name] = report_ci
spec.loader.exec_module(report_ci)


class ReportCiTests(unittest.TestCase):
    def test_report_lists_jobs_and_annotations(self):
        jobs = [
            {"name": "frontend", "conclusion": "failure", "html_url": "https://example/jobs/1"},
            {"name": "security", "conclusion": "success", "html_url": "https://example/jobs/2"},
        ]
        findings = [
            report_ci.Finding("frontend", "failure", "Undefined symbol", "desktop/js/app.js", 42)
        ]
        body = report_ci.render_report(
            "owner/repo", "123", "https://example/runs/123", "abcdef1234567890", jobs, findings
        )
        self.assertIn("frontend", body)
        self.assertIn("`failure`", body)
        self.assertIn("desktop/js/app.js:42", body)
        self.assertIn("Undefined symbol", body)
        self.assertTrue(report_ci.has_findings(jobs, findings))

    def test_clean_report_has_no_findings(self):
        jobs = [{"name": "frontend", "conclusion": "success", "html_url": "https://example/jobs/1"}]
        body = report_ci.render_report(
            "owner/repo", "123", "https://example/runs/123", "abcdef1234567890", jobs, []
        )
        self.assertIn("Aucune erreur ni alerte détectée", body)
        self.assertFalse(report_ci.has_findings(jobs, []))


if __name__ == "__main__":
    unittest.main()
