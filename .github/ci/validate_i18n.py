#!/usr/bin/env python3
import argparse,collections,json,re
from pathlib import Path
p=argparse.ArgumentParser();p.add_argument('--root',default='.');p.add_argument('--expected',required=True);p.add_argument('--require-languages',action='store_true');a=p.parse_args();root=Path(a.root);expected=[x for x in a.expected.split(',') if x];idir=root/'core/i18n';errors=[];notices=[];files={x.stem:x for x in idir.glob('*.json')} if idir.exists() else {}
if not files:
 (errors if a.require_languages else notices).append('core/i18n contains no translation files' if a.require_languages else 'i18n validation not applicable yet')
if a.require_languages:
 for lang in expected:
  if lang not in files:errors.append(f'missing core/i18n/{lang}.json')
rx=re.compile(r'(#(?:[A-Za-z0-9_]+)#|%(?:\d+\$)?[sdfox]|\{\{[^{}]+\}\}|<\/?[A-Za-z][^>]*>)')
for lang,path in files.items():
 try:data=json.loads(path.read_text())
 except Exception as exc:errors.append(f'{path}: invalid JSON: {exc}');continue
 if not isinstance(data,dict):errors.append(f'{path}: top-level must be an object');continue
 for source,entries in data.items():
  if not isinstance(entries,dict):errors.append(f'{path}: {source}: expected object');continue
  for key,value in entries.items():
   if not isinstance(key,str) or not isinstance(value,str):errors.append(f'{path}: {source}: keys/translations must be strings');continue
   if collections.Counter(rx.findall(key))!=collections.Counter(rx.findall(value)):errors.append(f'{path}: placeholder/tag mismatch for {key!r}')
info=root/'plugin_info/info.json'
if info.exists() and a.require_languages:
 try:
  langs=json.loads(info.read_text()).get('language',[]);missing=[x for x in expected if x not in langs]
  if missing:errors.append('info.json language missing: '+','.join(missing))
 except Exception as exc:errors.append(f'{info}: invalid JSON: {exc}')
for n in notices:print(f'::notice::{n}')
for e in errors:print(f'::error::{e}')
print(f'i18n validation: {len(files)} file(s), {len(errors)} error(s)');raise SystemExit(1 if errors else 0)
