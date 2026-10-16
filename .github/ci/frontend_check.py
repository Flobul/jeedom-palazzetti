#!/usr/bin/env python3
import re,subprocess,tempfile
from pathlib import Path
SKIP={"vendor","node_modules","3rdparty","third_party","tests",".git","jeedom-core"}
roots=[Path(x) for x in ("core","desktop","mobile","plugin_info") if Path(x).exists()]
def kept(p):return not any(part in SKIP for part in p.parts) and ".min." not in p.name
js=[p for root in roots for p in root.rglob("*.js") if kept(p)];css=[p for root in roots for p in root.rglob("*.css") if kept(p)];failed=False
for path in js:
 r=subprocess.run(["node","--check",str(path)],stdout=subprocess.PIPE,stderr=subprocess.STDOUT,text=True)
 if r.returncode:failed=True;print(r.stdout,end="")
checked=skipped=0
for root in roots:
 for path in list(root.rglob("*.php"))+list(root.rglob("*.html")):
  if not kept(path):continue
  source=path.read_text(errors="replace")
  source=re.sub(r"<\?php.*?\?>","0",source,flags=re.S)
  for index,script in enumerate(re.findall(r"<script\b[^>]*>(.*?)</script>",source,re.I|re.S),1):
   if "<?" in script or "{{" in script or re.search(r"#[A-Za-z0-9_]+#",script):skipped+=1;continue
   with tempfile.NamedTemporaryFile("w",suffix=".js",delete=False) as tmp:tmp.write(script);name=tmp.name
   r=subprocess.run(["node","--check",name],stdout=subprocess.PIPE,stderr=subprocess.STDOUT,text=True);Path(name).unlink(missing_ok=True);checked+=1
   if r.returncode:failed=True;print(f"Inline JavaScript syntax error: {path} block {index}\n{r.stdout}")
for path in css:
 text=re.sub(r"/\*.*?\*/","",path.read_text(errors="replace"),flags=re.S);depth=0
 for ch in text:
  if ch=="{":depth+=1
  elif ch=="}":depth-=1
  if depth<0:break
 if depth!=0:failed=True;print(f"CSS brace mismatch: {path} (balance {depth})")
jquery=[str(p) for p in js if re.search(r"(?:\$\s*\(|\bjQuery\s*\()",p.read_text(errors="replace"))]
if jquery:print("::warning::jQuery remains in maintained JavaScript (migration warning): "+", ".join(jquery))
print(f"Frontend syntax: {len(js)} JS, {len(css)} CSS, {checked} inline checked, {skipped} tokenized inline not applicable")
raise SystemExit(1 if failed else 0)
