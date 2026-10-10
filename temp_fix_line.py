from pathlib import Path
path = Path('app/Http/Controllers/FamilyImportController.php')
lines = path.read_text(encoding='utf-8').splitlines()
for idx, line in enumerate(lines):
    if line.strip() == "'marital_status' => ":
        lines[idx + 1] = "                ? 'يتيم'"
        break
path.write_text('\n'.join(lines) + '\n', encoding='utf-8')
