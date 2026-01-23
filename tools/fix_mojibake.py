from pathlib import Path
p = Path('admin/reports.php')
if not p.exists():
    print('file not found')
    raise SystemExit(1)
text = p.read_text(encoding='utf-8', errors='replace')
if 'ƒ,ñ' not in text:
    print('No "ƒ,ñ" sequences found.')
else:
    bkp = p.with_suffix('.php.bak')
    bkp.write_text(text, encoding='utf-8')
    new_text = text.replace('ƒ,ñ', '₱')
    p.write_text(new_text, encoding='utf-8')
    print('Replaced occurrences of "ƒ,ñ" with "₱" and wrote backup to', bkp)
