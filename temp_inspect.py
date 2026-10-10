from pathlib import Path
import re
text=Path('app/Http/Controllers/FamilyImportController.php').read_text(encoding='utf-8')
match=re.search(r"family_members_count' => \[(.*?)\]", text)
print(match.group(1).encode('unicode_escape'))
