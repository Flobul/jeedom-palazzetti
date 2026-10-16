#!/usr/bin/env python3
import re,subprocess
from pathlib import Path
files=[Path(x.decode()) for x in subprocess.run(['git','ls-files','-z'],stdout=subprocess.PIPE,check=True).stdout.split(b'\0') if x]
patterns={'private key':re.compile(r'-----BEGIN (?:RSA |EC |OPENSSH )?PRIVATE KEY-----'),'GitHub token':re.compile(r'\b(?:ghp|github_pat)_[A-Za-z0-9_]{20,}\b'),'AWS access key':re.compile(r'\bAKIA[0-9A-Z]{16}\b'),'Slack token':re.compile(r'\bxox[baprs]-[A-Za-z0-9-]{10,}\b')}
findings=[]
for path in files:
 if path.as_posix()=='.github/ci/secret_scan.py':continue
 if not path.is_file() or any(part in {'vendor','node_modules','3rdparty','third_party','tests'} for part in path.parts) or path.stat().st_size>2000000:continue
 try:text=path.read_text()
 except Exception:continue
 for name,rx in patterns.items():
  for match in rx.finditer(text):findings.append((path,name,text.count('\n',0,match.start())+1))
for path,name,line in findings:print(f'::error file={path},line={line}::{name} pattern detected')
print(f'Secret scan: {len(files)} tracked files, {len(findings)} high-confidence finding(s)');raise SystemExit(1 if findings else 0)
