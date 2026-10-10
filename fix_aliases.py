from collections import OrderedDict
from pathlib import Path
import re
aliases = OrderedDict([
    ('head_name', ['head_name', 'name', 'orphan_name', 'orphan full name', 'family head name', 'اسم اليتيم', 'اسم رب الأسرة', 'اسم رب الاسرة', 'ÇÓã ÇáíÊíã', 'ÇÓã ÑÈ ÇáÃÓÑÉ', 'ÇÓã ÑÈ ÇáÇÓÑÉ', 'Ø§Ø³Ù… Ø§Ù„ÙŠØªÙŠÙ…', 'Ø§Ø³Ù… Ø±Ø¨ Ø§Ù„Ø§Ø³Ø±Ø©', 'Ø§Ø³Ù… Ø±Ø¨ Ø§Ù„Ø£Ø³Ø±Ø©']),
    ('head_national_id', ['head_national_id', 'national_id', 'orphan_national_id', 'orphan_id', 'family head id', 'رقم هوية اليتيم', 'هوية رب الأسرة', 'هوية رب الاسرة', 'رقم هوية رب الأسرة', 'رقم هوية رب الاسرة', 'ÑÞã åæíÉ ÇáíÊíã', 'åæíÉ ÑÈ ÇáÃÓÑÉ', 'åæíÉ ÑÈ ÇáÇÓÑÉ', 'ÑÞã åæíÉ ÑÈ ÇáÃÓÑÉ', 'ÑÞã åæíÉ ÑÈ ÇáÇÓÑÉ', 'Ø±Ù‚Ù… Ù‡ÙˆÙŠØ© Ø§Ù„ÙŠØªÙŠÙ…', 'Ù‡ÙˆÙŠØ© Ø±Ø¨ Ø§Ù„Ø§Ø³Ø±Ø©', 'Ù‡ÙˆÙŠØ© Ø±Ø¨ Ø§Ù„Ø£Ø³Ø±Ø©', 'Ø±Ù‚Ù… Ù‡ÙˆÙŠØ© Ø±Ø¨ Ø§Ù„Ø§Ø³Ø±Ø©']),
    ('head_gender', ['head_gender', 'gender', 'orphan_gender', 'الجنس', 'sex', 'Ø§Ù„Ø¬Ù†Ø³']),
    ('head_birth_date', ['head_birth_date', 'orphan_birth_date', 'تاريخ ميلاد اليتيم', 'تاريخ ميلاد رب الأسرة', 'تاريخ ميلاد رب الاسرة', 'ØªØ§Ø±ÙŠØ® Ù…ÙŠÙ„Ø§Ø¯ Ø§Ù„ÙŠØªÙŠÙ…', 'ØªØ§Ø±ÙŠØ® Ù…ÙŠÙ„Ø§Ø¯ Ø±Ø¨ Ø§Ù„Ø§Ø³Ø±Ø©', 'ØªØ§Ø±ÙŠØ® Ù…ÙŠÙ„Ø§Ø¯ Ø±Ø¨ Ø§Ù„Ø£Ø³Ø±Ø©']),
    ('marital_status', ['marital_status', 'الحالة الاجتماعية', 'Ø§Ù„Ø­Ø§Ù„Ø© Ø§Ù„Ø§Ø¬ØªÙ…Ø§Ø¹ÙŠØ©']),
    ('spouse_name', ['spouse_name', 'اسم الزوج/ة', 'اسم الزوجة', 'اسم الزوج', 'Ø§Ø³Ù… Ø§Ù„Ø²ÙˆØ¬/Ø©', 'Ø§Ø³Ù… Ø§Ù„Ø²ÙˆØ¬Ø©', 'Ø§Ø³Ù… Ø§Ù„Ø²ÙˆØ¬']),
    ('spouse_national_id', ['spouse_national_id', 'هوية الزوج/ة', 'هوية الزوجة', 'هوية الزوج', 'Ù‡ÙˆÙŠØ© Ø§Ù„Ø²ÙˆØ¬/Ø©', 'Ù‡ÙˆÙŠØ© Ø§Ù„Ø²ÙˆØ¬Ø©', 'Ù‡ÙˆÙŠØ© Ø§Ù„Ø²ÙˆØ¬']),
    ('spouse_birth_date', ['spouse_birth_date', 'تاريخ ميلاد الزوج/ة', 'تاريخ ميلاد الزوجة', 'تاريخ ميلاد الزوج', 'ØªØ§Ø±ÙŠØ® Ù…ÙŠÙ„Ø§Ø¯ Ø§Ù„Ø²ÙˆØ¬/Ø©', 'ØªØ§Ø±ÙŠØ® Ù…ÙŠÙ„Ø§Ø¯ Ø§Ù„Ø²ÙˆØ¬Ø©', 'ØªØ§Ø±ÙŠØ® Ù…ÙŠÙ„Ø§Ø¯ Ø§Ù„Ø²ÙˆØ¬']),
    ('governorate', ['governorate', 'orphan_governorate', 'المحافظة', 'Ø§Ù„Ù…Ø­Ø§ÙØ¸Ø©']),
    ('area', ['area', 'orphan_area', 'المنطقة', 'Ø§Ù„Ù…Ù†Ø·Ù‚Ø©']),
    ('family_members_count', ['family_members_count', 'عدد الافراد', 'عدد الأفراد', 'Ø¹Ø¯Ø¯ Ø§Ù„Ø§ÙØ±Ø§Ø¯', 'Ø¹Ø¯Ø¯ Ø§Ù„Ø£ØºØ±Ø§Ø¯']),
    ('mobile', ['mobile', 'orphan_mobile', 'جوال اليتيم', 'رقم الجوال', 'Ø¬ÙˆØ§Ù„ Ø§Ù„ÙŠØªÙŠÙ…', 'Ø±Ù‚Ù… Ø§Ù„Ø¬ÙˆØ§Ù„']),
    ('war_injury', ['war_injury', 'اصابة حرب', 'إصابة حرب', 'Ø§ØµØ§Ø¨Ø© Ø­Ø±Ø¨', 'Ø¥ØµØ§Ø¨Ø© Ø­Ø±Ø¨']),
    ('chronic_diseases', ['chronic_diseases', 'orphan_chronic_diseases', 'الأمراض المزمنة لليتيم', 'امراض مزمنة', 'أمراض مزمنة', 'Ø§Ù„Ø£Ù…Ø±Ø§Ø¶ Ø§Ù„Ù…Ø²Ù…Ù†Ø© Ù„Ù„ÙŠØªÙŠÙ…', 'Ø§Ù…Ø±Ø§Ø¶ Ù…Ø²Ù…Ù†Ø©', 'Ø£Ù…Ø±Ø§Ø¶ Ù…Ø²Ù…Ù†Ø©']),
    ('disability', ['disability', 'orphan_disability', 'الإعاقات لليتيم', 'اعاقة', 'إعاقة', 'Ø§Ù„Ø¥Ø¹Ø§Ù‚Ø§Øª Ù„Ù„ÙŠØªÙŠÙ…', 'Ø§Ø¹Ø§Ù‚Ø©', 'Ø¥Ø¹Ø§Ù‚Ø©']),
    ('employment_status', ['employment_status', 'حالة العمل', 'Ø­Ø§Ù„Ø© Ø§Ù„Ø¹Ù…Ù„']),
    ('notes', ['notes', 'ملاحظات', 'ملاحظات عامة', 'ملاحظات اليتيم']),
    ('head_age', ['head_age', 'عمر رب الاسرة', 'عمر رب الأسرة']),
    ('spouse_age', ['spouse_age', 'عمر الزوج/ة', 'عمر الزوجة', 'عمر الزوج']),
    ('benefits_from_institution', ['benefits_from_institution', 'الاستفادات من المؤسسة', 'استفادات اليتيم']),
    ('guardian_name', ['guardian_name', 'اسم الوصي']),
    ('guardian_national_id', ['guardian_national_id', 'guardian_id', 'هوية الوصي', 'رقم هوية الوصي']),
    ('guardian_mobile', ['guardian_mobile', 'جوال الوصي']),
    ('guardian_relation', ['guardian_relation', 'صلة القرابة', 'العلاقة مع اليتيم']),
    ('siblings_count', ['siblings_count', 'عدد الإخوة']),
    ('father_death_date', ['father_death_date', 'تاريخ وفاة الأب']),
    ('mother_death_date', ['mother_death_date', 'تاريخ وفاة الأم']),
    ('education_status', ['education_status', 'الحالة الدراسية']),
    ('detailed_address', ['detailed_address', 'عنوان السكن بالتفصيل']),
    ('contact_phones', ['contact_phones', 'أرقام الهاتف/الجوال', 'ارقام الهاتف/الجوال']),
    ('institution_id', ['institution_id']),
    ('institution_name', ['institution_name']),
    ('institution', ['institution', 'المؤسسة']),
    ('administrator_id', ['administrator_id']),
    ('data_entry_id', ['data_entry_id']),
])
path = Path('app/Http/Controllers/FamilyImportController.php')
text = path.read_text(encoding='utf-8')
block_lines = ["         = ["]
for key, values in aliases.items():
    block_lines.append(f"            '{key}' => ['" + "', '".join(values) + "'],")
block_lines.append("        ];")
new_block = "\n".join(block_lines)
text = re.sub(r"\ = \[.*?\];", new_block, text, flags=re.S)
path.write_text(text, encoding='utf-8')
