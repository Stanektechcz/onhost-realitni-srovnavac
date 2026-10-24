#!/usr/bin/env python3
from pathlib import Path
import json, sys
root=Path(__file__).resolve().parents[1]
folder=root/'docs'/'onhost'
required=['README.md','PRODUCT.md','FEATURE_MATRIX.md','ARCHITECTURE.md','TECH_STACK.md','DATA_MODEL.md','API_AND_EVENTS.md','CONFIGURATION.md','INTEGRATIONS.md','SECURITY_AND_PRIVACY.md','DESIGN_SYSTEM.md','ENGINEERING_STANDARDS.md','TEAM_PLAYBOOK.md','QUALITY_AND_TESTS.md','OPERATIONS_RUNBOOK.md','ROADMAP.md','AI_AGENT_GUIDE.md','ONBOARDING.md','HANDOFF.md','ADR/0001-boundaries.md','project-manifest.json']
errors=['Missing '+x for x in required if not (folder/x).is_file()]
try:
 m=json.loads((folder/'project-manifest.json').read_text(encoding='utf-8'))
 assert m['implementation_status'] in {'existing_code','archive','concept','prototype'}
 assert m['project'] and m['vision'] and m['modules']
except (ValueError, KeyError, AssertionError, FileNotFoundError) as e:
 errors.append('Invalid manifest '+str(e))
for f in folder.rglob('*.md'):
 txt=f.read_text(encoding='utf-8')
 if txt.count(chr(96)*3)%2: errors.append('Unbalanced fences: '+str(f))
if errors:
 print('\n'.join(errors));sys.exit(1)
print('ONHOST docs structure: PASS')
