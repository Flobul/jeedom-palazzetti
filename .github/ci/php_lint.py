#!/usr/bin/env python3
import subprocess
from pathlib import Path
SKIP={"vendor","node_modules","3rdparty","third_party","tests",".git",".github","jeedom-core"}
files=[p for p in Path('.').rglob('*.php') if not any(part in SKIP for part in p.parts)]
if not files: print('PHP syntax: not applicable'); raise SystemExit(0)
failed=False
for path in files:
 r=subprocess.run(['php','-l',str(path)],text=True,stdout=subprocess.PIPE,stderr=subprocess.STDOUT)
 if r.returncode: failed=True; print(r.stdout,end='')
print(f'PHP syntax: checked {len(files)} maintained files')
raise SystemExit(1 if failed else 0)
