#!/usr/bin/env python3
import json
from pathlib import Path
import unittest

ROOT = Path(__file__).resolve().parents[2]
WORKFLOWS = ROOT / '.github' / 'workflows'


class TranslationWorkflowSeparationTests(unittest.TestCase):
    def test_blocking_ci_uses_read_only_translation_validation(self):
        ci = (WORKFLOWS / 'ci.yml').read_text(encoding='utf-8')
        self.assertIn('uses: ./.github/workflows/check-translation.yml', ci)
        translation_job = ci[ci.index('  translation:'):ci.index('  report:')]
        self.assertNotIn('contents: write', translation_job)
        self.assertNotIn('pull-requests: write', translation_job)
        self.assertNotIn('secrets: inherit', translation_job)

    def test_validation_workflow_is_secretless_and_read_only(self):
        workflow = (WORKFLOWS / 'check-translation.yml').read_text(encoding='utf-8')
        config = json.loads((ROOT / '.github' / 'ci' / 'ci-config.json').read_text())
        expected = ','.join(config['i18n']['languages'])
        self.assertIn('workflow_call:', workflow)
        self.assertIn('contents: read', workflow)
        self.assertIn(f'--expected {expected}', workflow)
        self.assertIn('--require-languages', workflow)
        self.assertNotIn('DEEPL_API_KEY', workflow)
        self.assertNotIn('create-pull-request', workflow)
        self.assertNotIn('contents: write', workflow)

    def test_generation_is_manual_independent_and_skips_without_secret(self):
        workflow = (WORKFLOWS / 'translation.yml').read_text(encoding='utf-8')
        self.assertIn('workflow_dispatch:', workflow)
        self.assertNotIn('workflow_call:', workflow)
        self.assertIn('id: deepl', workflow)
        self.assertIn('configured=false', workflow)
        self.assertIn("steps.deepl.outputs.configured == 'true'", workflow)
        self.assertIn('peter-evans/create-pull-request@v8', workflow)
        self.assertNotIn('::error::Translation blocked', workflow)


if __name__ == '__main__':
    unittest.main()
