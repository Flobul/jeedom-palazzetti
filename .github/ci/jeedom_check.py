#!/usr/bin/env python3
import json,re
from pathlib import Path
cfg=json.loads(Path('.github/ci/ci-config.json').read_text());errors=[];warnings=[];info_path=Path('plugin_info/info.json')
try:info=json.loads(info_path.read_text())
except Exception as exc:errors.append(f'plugin_info/info.json invalid: {exc}');info={}
pid=info.get('id');expected=cfg['plugin']['id']
if not isinstance(pid,str) or not pid.strip():errors.append('plugin_info/info.json: non-empty id is required')
if pid and pid!=expected:errors.append(f'configured id {expected!r} differs from info.json id {pid!r}')
classes=list(Path('core/class').glob('*.class.php')) if Path('core/class').exists() else []
if not classes:warnings.append('No plugin class file found under core/class')
elif pid and pid.lower() not in {p.name[:-10].lower() for p in classes}:warnings.append(f'No class filename exactly matching plugin id {pid}')
install=Path('plugin_info/install.php')
if install.exists() and pid:
 text=install.read_text(errors='replace')
 for suffix in ('install','update','remove'):
  if not re.search(rf'function\s+{re.escape(pid)}_{suffix}\s*\(',text,re.I):warnings.append(f'install.php has no {pid}_{suffix} function')
for root in ('core','desktop','mobile','plugin_info'):
 p=Path(root)
 if not p.exists():continue
 for f in p.rglob('*.php'):
  if any(x in f.parts for x in ('vendor','3rdparty','third_party')):continue
  text=f.read_text(errors='replace')
  for duplicated in ('include_file','isConnect','init','sendVarToJS'):
   if re.search(rf'function\s+{duplicated}\s*\(',text):warnings.append(f'{f}: duplicates Jeedom global helper {duplicated}')
  if 'addCmdToTable(' in text and 'logicalId' not in text and 'getLogicalId' not in text:warnings.append(f'{f}: addCmdToTable needs manual logicalId consistency review')
for f in Path('desktop').rglob('*.php') if Path('desktop').exists() else []:
 text=f.read_text(errors='replace');legacy=len(re.findall(r'(?<!data-)\btitle\s*=\s*["\']',text))
 if legacy:warnings.append(f'{f}: {legacy} title attribute(s) need data-title review')
for msg in sorted(set(warnings)):print(f'::warning::{msg}')
for msg in errors:print(f'::error::{msg}')
print(f'Jeedom checks: {len(errors)} error(s), {len(set(warnings))} warning(s)');raise SystemExit(1 if errors else 0)
