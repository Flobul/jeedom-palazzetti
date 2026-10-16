#!/usr/bin/env python3
import argparse
import json
from pathlib import Path

parser = argparse.ArgumentParser()
parser.add_argument('--root', default='.')
parser.add_argument('--languages', required=True)
args = parser.parse_args()
path = Path(args.root) / 'plugin_info/info.json'
data = json.loads(path.read_text())
data['language'] = [language for language in args.languages.split(',') if language]
path.write_text(json.dumps(data, ensure_ascii=False, indent='\t') + '\n')
