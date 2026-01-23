import sys
from pathlib import Path
p = Path('admin/reports.php')
if not p.exists():
    print('file not found:', p)
    sys.exit(1)
data = p.read_bytes()
max_show = 50
found = 0
for i, b in enumerate(data):
    if b > 127:
        # compute line and column
        line = data.count(b'\n', 0, i) + 1
        col = i - data.rfind(b'\n', 0, i)
        start = max(0, i-30)
        end = min(len(data), i+30)
        snippet = data[start:end]
        try:
            decoded = snippet.decode('utf-8')
        except Exception:
            decoded = snippet.decode('utf-8', errors='replace')
        print(f'Found byte 0x{b:02x} at index {i}, line {line}, col {col}')
        print('Context:')
        print(decoded)
        print('-'*40)
        found += 1
        if found >= max_show:
            break
if found == 0:
    print('No non-ASCII bytes found')
