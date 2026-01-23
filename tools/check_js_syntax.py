import sys
from pathlib import Path
import subprocess
from html.parser import HTMLParser

ROOT = Path(__file__).resolve().parents[1]
src = ROOT / 'staff' / 'reports.php'
out = ROOT / 'tools' / 'combined_scripts.js'

text = src.read_text(encoding='utf-8', errors='replace')


class ScriptExtractor(HTMLParser):
    def __init__(self):
        super().__init__()
        self.scripts = []
        self._in_script = False
        self._current = []

    def handle_starttag(self, tag, attrs):
        if tag.lower() == 'script':
            # check for src attribute
            has_src = any(name.lower() == 'src' for name, _ in attrs)
            if not has_src:
                self._in_script = True

    def handle_endtag(self, tag):
        if tag.lower() == 'script' and self._in_script:
            self.scripts.append(''.join(self._current))
            self._current = []
            self._in_script = False

    def handle_data(self, data):
        if self._in_script:
            self._current.append(data)


parser = ScriptExtractor()
parser.feed(text)

if not parser.scripts:
    print('No inline <script> blocks found.')
    sys.exit(0)

combined_js = '\n\n'.join(parser.scripts)
out.write_text(combined_js, encoding='utf-8')
print(f'Wrote combined JS to {out}')

# Run node --check if available
try:
    res = subprocess.run(['node', '--check', str(out)], capture_output=True, text=True)
except FileNotFoundError:
    print('Node not found in PATH; cannot run syntax check.')
    sys.exit(2)

if res.returncode == 0:
    print('Node syntax check passed — no syntax errors detected.')
    sys.exit(0)
else:
    print('Node syntax check failed; output:')
    print(res.stderr)
    sys.exit(res.returncode)
