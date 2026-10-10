<?php

namespace App\Http\Controllers;

use App\Models\Family;
use App\Models\Institution;
use App\Models\Orphan;
use App\Models\User;
use App\Support\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class FamilyImportController extends Controller
{
    private const DELIMITER_CANDIDATES = [',', ';', "\t", '|', '،'];

    public function import(Request $request): RedirectResponse
    {
        @set_time_limit(300);
        @ini_set('max_execution_time', '300');

        $user = $request->user();
        $data = $request->validate([
            'file' => ['nullable', 'file'],
            'files' => ['nullable', 'array'],
            'files.*' => ['file'],
            'institution_id' => [$user->isDeveloper() ? 'required' : 'nullable', 'integer', 'exists:institutions,id'],
            'import_type' => ['nullable', 'in:family,orphan'],
        ]);

        $importType = (string) ($data['import_type'] ?? 'family');
        $defaultInstitutionId = $this->toNullableInt($data['institution_id'] ?? null);
        $files = collect($data['files'] ?? []);
        if (! empty($data['file'])) {
            $files->prepend($data['file']);
        }
        $files = $files->filter()->values();
        if ($files->isEmpty()) {
            return back()->withErrors(['file' => 'Cannot read uploaded file.']);
        }

        $rows = $created = $updated = $unchanged = 0;
        $errors = [];
        $effectiveImportTypes = [];

        foreach ($files as $fileIndex => $uploadedFile) {
            $result = $this->processImportFile($uploadedFile, $user, $defaultInstitutionId, $importType);
            $rows += $result['rows'];
            $created += $result['created'];
            $updated += $result['updated'];
            $unchanged += $result['unchanged'];
            $effectiveImportTypes[] = (string) ($result['import_type'] ?? $importType);
            foreach ($result['errors'] as $error) {
                $errors[] = 'file '.($fileIndex + 1).': '.$error;
            }
        }

        $effectiveImportTypes = collect($effectiveImportTypes)->filter()->unique()->values()->all();
        $entityLabel = count($effectiveImportTypes) === 1 ? $effectiveImportTypes[0] : 'mixed';

        ActivityLogger::log($request, 'families.imported', null, [
            'import_type' => $entityLabel,
            'requested_import_type' => $importType,
            'rows' => $rows,
            'created' => $created,
            'updated' => $updated,
            'unchanged' => $unchanged,
            'errors_count' => count($errors),
            'files_count' => $files->count(),
        ]);

        $message = "Import completed. Processed {$files->count()} file(s), created {$created}, updated {$updated}, unchanged {$unchanged} {$entityLabel} records from {$rows} rows.";
        if ($errors !== []) {
            $message .= ' Errors: '.implode(' | ', array_slice($errors, 0, 5));
        }

        return back()->with('status', $message);
    }

    private function processImportFile($uploadedFile, User $user, ?int $defaultInstitutionId, string $importType = 'family'): array
    {
        if ($this->isUnsupportedSpreadsheetFile($uploadedFile)) {
            return [
                'rows' => 0,
                'created' => 0,
                'updated' => 0,
                'unchanged' => 0,
                'errors' => ['Unsupported file format. Please upload CSV (UTF-8) instead of XLS/XLSX.'],
                'import_type' => $importType,
            ];
        }

        $lines = @file($uploadedFile->getRealPath(), FILE_IGNORE_NEW_LINES);
        if (! is_array($lines) || $lines === []) {
            return [
                'rows' => 0,
                'created' => 0,
                'updated' => 0,
                'unchanged' => 0,
                'errors' => ['Cannot read uploaded file.'],
                'import_type' => $importType,
            ];
        }

        [$headerIndex, $delimiter, $header] = $this->detectHeader($lines);
        if ($header === []) {
            return [
                'rows' => 0,
                'created' => 0,
                'updated' => 0,
                'unchanged' => 0,
                'errors' => ['CSV header could not be detected.'],
                'import_type' => $importType,
            ];
        }

        $effectiveImportType = $this->inferImportType($importType, $header);

        if (! in_array('head_name', $header, true) || ! in_array('head_national_id', $header, true)) {
            $firstDataLine = $this->firstNonEmptyLine(array_slice($lines, max($headerIndex + 1, 0)));
            if ($firstDataLine === null) {
                return [
                    'rows' => 0,
                    'created' => 0,
                    'updated' => 0,
                    'unchanged' => 0,
                    'errors' => ['CSV is empty.'],
                    'import_type' => $effectiveImportType,
                ];
            }

            $delimiter = $this->detectBestDelimiterForLine($this->normalizeCsvLine($firstDataLine), $delimiter);
            $firstRowColumns = str_getcsv($this->normalizeCsvLine($firstDataLine), $delimiter);
            $header = $this->buildFallbackHeader(count($firstRowColumns));
            $headerIndex = -1;
        }

        $expectedColumns = count($header);
        $rows = $created = $updated = $unchanged = 0;
        $errors = [];

        $startLineIndex = max($headerIndex + 1, 1);
        for ($lineIndex = $startLineIndex; $lineIndex < count($lines); $lineIndex++) {
            $line = $this->normalizeCsvLine((string) $lines[$lineIndex]);
            if (trim($line) === '') {
                continue;
            }

            $rows++;
            $row = str_getcsv($line, $delimiter);
            if (count($row) !== $expectedColumns) {
                $row = $this->tryFixColumns($line, $expectedColumns, $delimiter);
            }
            if (count($row) < $expectedColumns) {
                $row = array_pad($row, $expectedColumns, null);
            }
            if (count($row) !== $expectedColumns) {
                $errors[] = "row {$rows}: column count mismatch";
                continue;
            }

            $item = array_combine($header, $row) ?: [];
            if ($item === []) {
                $errors[] = "row {$rows}: invalid row data";
                continue;
            }

            try {
                $payload = $this->mapCsvRow($user, $item, $defaultInstitutionId, $effectiveImportType);
                $result = $effectiveImportType === 'orphan'
                    ? $this->createOrUpdateOrphan($payload)
                    : $this->createOrUpdateFamily($payload);
                if ($result === 'created') {
                    $created++;
                } elseif ($result === 'updated') {
                    $updated++;
                } else {
                    $unchanged++;
                }
            } catch (\Throwable $e) {
                $errors[] = "row {$rows}: ".$e->getMessage();
            }
        }

        return [
            'rows' => $rows,
            'created' => $created,
            'updated' => $updated,
            'unchanged' => $unchanged,
            'errors' => $errors,
            'import_type' => $effectiveImportType,
        ];
    }

    private function inferImportType(string $requestedType, array $header): string
    {
        if ($requestedType === 'orphan') {
            return 'orphan';
        }

        $orphanMarkers = ['guardian_name', 'guardian_national_id', 'guardian_mobile', 'guardian_relation', 'siblings_count', 'father_death_date', 'mother_death_date', 'education_status'];
        foreach ($orphanMarkers as $marker) {
            if (in_array($marker, $header, true)) {
                return 'orphan';
            }
        }

        return 'family';
    }

    private function isUnsupportedSpreadsheetFile($uploadedFile): bool
    {
        $extension = Str::lower((string) $uploadedFile->getClientOriginalExtension());
        if (in_array($extension, ['xlsx', 'xls'], true)) {
            return true;
        }

        $path = $uploadedFile->getRealPath();
        if (! is_string($path) || $path === '' || ! is_file($path)) {
            return false;
        }

        $signature = @file_get_contents($path, false, null, 0, 8);
        if (! is_string($signature) || $signature === '') {
            return false;
        }

        if (str_starts_with($signature, 'PK')) {
            return true;
        }

        $xlsSignature = "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1";
        return strncmp($signature, $xlsSignature, 8) === 0;
    }

    private function detectHeader(array $lines): array
    {
        $best = [-1, ',', [], -1];

        foreach ($lines as $index => $line) {
            if ($index > 20) {
                break;
            }

            $line = $this->normalizeCsvLine((string) $line);
            if (trim($line) === '') {
                continue;
            }

            foreach (self::DELIMITER_CANDIDATES as $candidate) {
                $columns = str_getcsv($line, $candidate);
                if ($columns === []) {
                    continue;
                }

                $header = array_map(fn ($h) => $this->resolveHeaderKey((string) $h), $columns);
                $score = 0;
                if (in_array('head_name', $header, true)) {
                    $score += 5;
                }
                if (in_array('head_national_id', $header, true)) {
                    $score += 5;
                }
                if (count($header) > 1) {
                    $score += 1;
                }

                if ($score > $best[3]) {
                    $best = [$index, $candidate, $header, $score];
                }
            }
        }

        return [$best[0], $best[1], is_array($best[2]) ? $best[2] : []];
    }

    private function tryFixColumns(string $line, int $expectedColumns, string $defaultDelimiter): array
    {
        $line = $this->normalizeCsvLine($line);

        if ($expectedColumns <= 2) {
            $bestDelimiter = $this->detectBestDelimiterForLine($line, $defaultDelimiter);
            $bestRow = str_getcsv($line, $bestDelimiter);
            if (count($bestRow) > $expectedColumns) {
                return $bestRow;
            }
        }

        foreach (self::DELIMITER_CANDIDATES as $candidate) {
            $row = str_getcsv($line, $candidate);
            if (count($row) === $expectedColumns) {
                return $row;
            }
        }

        return str_getcsv($line, $defaultDelimiter);
    }

    private function firstNonEmptyLine(array $lines): ?string
    {
        foreach ($lines as $line) {
            if (trim((string) $line) !== '') {
                return (string) $line;
            }
        }

        return null;
    }

    private function detectBestDelimiterForLine(string $line, string $fallback = ','): string
    {
        $bestDelimiter = $fallback;
        $bestColumnsCount = 0;

        foreach (self::DELIMITER_CANDIDATES as $candidate) {
            $columnsCount = count(str_getcsv($line, $candidate));
            if ($columnsCount > $bestColumnsCount) {
                $bestColumnsCount = $columnsCount;
                $bestDelimiter = $candidate;
            }
        }

        return $bestDelimiter;
    }

    private function buildFallbackHeader(int $columnCount): array
    {
        $base = ['head_name', 'head_national_id'];

        if ($columnCount <= 2) {
            return array_slice($base, 0, $columnCount);
        }

        for ($i = 2; $i < $columnCount; $i++) {
            $base[] = 'extra_'.$i;
        }

        return $base;
    }

    private function normalizeCsvLine(string $line): string
    {
        return str_replace(['،', '٬', 'طŒ'], ',', $line);
    }

    private function mapCsvRow(User $user, array $item, ?int $defaultInstitutionId = null, string $importType = 'family'): array
    {
        $headName = trim((string) ($item['head_name'] ?? $item['name'] ?? ''));
        $headNationalId = $this->normalizeNationalId((string) ($item['head_national_id'] ?? $item['national_id'] ?? ''));

        if ($headName === '' || $headNationalId === '') {
            throw new \RuntimeException('head_name and head_national_id are required');
        }

        $institutionId = $this->resolveInstitutionId($user, $item, $defaultInstitutionId);

        $isOrphanImport = $importType === 'orphan';
        $guardianName = trim((string) ($item['guardian_name'] ?? ''));
        $guardianNationalId = $this->normalizeNationalId((string) ($item['guardian_national_id'] ?? ''));
        $guardianMobile = trim((string) ($item['guardian_mobile'] ?? ''));
        $guardianRelation = $this->normalizeGuardianRelation($item['guardian_relation'] ?? null);

        if ($isOrphanImport) {
            // guardian_relation is optional for orphan imports; other guardian details remain mandatory
            if ($guardianName === '' || $guardianNationalId === '' || $guardianMobile === '') {
                throw new \RuntimeException((string) __('ui.import_orphan_guardian_required'));
            }

            if (! preg_match('/^\d{9}$/', $guardianNationalId)) {
                throw new \RuntimeException('guardian_national_id must be exactly 9 digits for orphan import');
            }

            if (! preg_match('/^\d{9}$/', $guardianMobile)) {
                throw new \RuntimeException('guardian_mobile must be exactly 9 digits for orphan import');
            }
        }

        return [
            'institution_id' => $institutionId,
            'administrator_id' => $user->isAdmin()
                ? $user->id
                : $this->toNullableInt($item['administrator_id'] ?? null, $user->created_by),
            'data_entry_id' => $user->isDataEntry()
                ? $user->id
                : $this->toNullableInt($item['data_entry_id'] ?? null),
            'head_name' => $headName,
            'head_national_id' => $headNationalId,
            'head_gender' => $this->normalizeGender($item['head_gender'] ?? null),
            'head_birth_date' => $item['head_birth_date'] ?? null,
            'marital_status' => $isOrphanImport
                ? 'يتيم'
                : ($item['marital_status'] ?? null),
            'spouse_name' => $item['spouse_name'] ?? null,
            'spouse_national_id' => $item['spouse_national_id'] ?? null,
            'spouse_birth_date' => $item['spouse_birth_date'] ?? null,
            'guardian_name' => $guardianName !== '' ? $guardianName : null,
            'guardian_national_id' => $guardianNationalId !== '' ? $guardianNationalId : null,
            'guardian_mobile' => $guardianMobile !== '' ? $guardianMobile : null,
            'guardian_relation' => $guardianRelation,
            'siblings_count' => is_numeric($item['siblings_count'] ?? null) ? max(0, (int) $item['siblings_count']) : null,
            'father_death_date' => $item['father_death_date'] ?? null,
            'mother_death_date' => $item['mother_death_date'] ?? null,
            'education_status' => $item['education_status'] ?? null,
            'detailed_address' => $item['detailed_address'] ?? null,
            'contact_phones' => $item['contact_phones'] ?? null,
            'governorate' => $item['governorate'] ?? null,
            'area' => $item['area'] ?? null,
            'family_members_count' => max(1, (int) ($item['family_members_count'] ?? 1)),
            'mobile' => $item['mobile'] ?? null,
            'war_injury' => $this->parseBoolean($item['war_injury'] ?? '0'),
            'chronic_diseases' => $item['chronic_diseases'] ?? null,
            'disability' => $item['disability'] ?? null,
            'employment_status' => $item['employment_status'] ?? null,
            'notes' => $item['notes'] ?? null,
            'head_age' => is_numeric($item['head_age'] ?? null) ? (int) $item['head_age'] : null,
            'spouse_age' => is_numeric($item['spouse_age'] ?? null) ? (int) $item['spouse_age'] : null,
            'benefits_from_institution' => $item['benefits_from_institution'] ?? null,
        ];
    }

    private function createOrUpdateOrphan(array $payload): string
    {
        $lookup = [
            'institution_id' => $payload['institution_id'],
            'head_national_id' => $payload['head_national_id'],
        ];

        $values = [
            'administrator_id' => $payload['administrator_id'] ?? null,
            'data_entry_id' => $payload['data_entry_id'] ?? null,
            'head_name' => $payload['head_name'] ?? null,
            'head_gender' => $payload['head_gender'] ?? null,
            'head_birth_date' => $payload['head_birth_date'] ?? null,
            'marital_status' => 'يتيم',
            'guardian_name' => $payload['guardian_name'] ?? null,
            'guardian_national_id' => $payload['guardian_national_id'] ?? null,
            'guardian_mobile' => $payload['guardian_mobile'] ?? null,
            'guardian_relation' => $payload['guardian_relation'] ?? null,
            'siblings_count' => $payload['siblings_count'] ?? null,
            'father_death_date' => $payload['father_death_date'] ?? null,
            'mother_death_date' => $payload['mother_death_date'] ?? null,
            'education_status' => $payload['education_status'] ?? null,
            'detailed_address' => $payload['detailed_address'] ?? null,
            'contact_phones' => $payload['contact_phones'] ?? null,
            'governorate' => $payload['governorate'] ?? null,
            'area' => $payload['area'] ?? null,
            'family_members_count' => max(1, (int) ($payload['family_members_count'] ?? 1)),
            'mobile' => $payload['mobile'] ?? null,
            'chronic_diseases' => $payload['chronic_diseases'] ?? null,
            'disability' => $payload['disability'] ?? null,
            'notes' => $payload['notes'] ?? null,
            'head_age' => is_numeric($payload['head_age'] ?? null) ? (int) $payload['head_age'] : null,
        ];

        $orphan = Orphan::query()->where($lookup)->first();
        if (! $orphan) {
            Orphan::query()->create(array_merge($lookup, $values));
            return 'created';
        }

        $current = collect($values)->map(fn ($value) => $value === '' ? null : $value)->all();
        $original = collect($values)->map(fn ($_v, $key) => $orphan->{$key})->all();
        if ($current == $original) {
            return 'unchanged';
        }

        $orphan->update($values);
        return 'updated';
    }

    private function resolveInstitutionId(User $user, array $item, ?int $defaultInstitutionId = null): ?int
    {
        if (! $user->isDeveloper()) {
            return $user->institution_id;
        }

        $institutionId = $this->toNullableInt($item['institution_id'] ?? null);
        if ($institutionId !== null) {
            return $institutionId;
        }

        if ($defaultInstitutionId !== null) {
            return $defaultInstitutionId;
        }

        $name = trim((string) ($item['institution_name'] ?? $item['institution'] ?? ''));
        if ($name === '') {
            throw new \RuntimeException('institution_id or institution name is required for developer import');
        }

        $institution = Institution::query()->where('name', $name)->first();
        if (! $institution) {
            throw new \RuntimeException("institution not found: {$name}");
        }

        return $institution->id;
    }

    private function resolveHeaderKey(string $rawHeader): string
    {
        $rawHeader = ltrim($rawHeader, "\xEF\xBB\xBF");
        $normalized = $this->normalizeHeader($rawHeader);

        $aliases = [
            'head_name' => ['head_name', 'name', 'orphan_name', 'orphan full name', 'family head name', 'اسم اليتيم', 'اسم رب الأسرة', 'اسم رب الاسرة'],
            'head_national_id' => ['head_national_id', 'national_id', 'orphan_national_id', 'orphan_id', 'family head id', 'رقم هوية اليتيم', 'هوية رب الأسرة', 'هوية رب الاسرة', 'رقم هوية رب الأسرة', 'رقم هوية رب الاسرة'],
            'head_gender' => ['head_gender', 'gender', 'orphan_gender', 'الجنس', 'sex'],
            'head_birth_date' => ['head_birth_date', 'orphan_birth_date', 'تاريخ ميلاد اليتيم', 'تاريخ ميلاد رب الأسرة', 'تاريخ ميلاد رب الاسرة'],
            'marital_status' => ['marital_status', 'الحالة الاجتماعية'],
            'spouse_name' => ['spouse_name', 'اسم الزوج/ة', 'اسم الزوجة', 'اسم الزوج'],
            'spouse_national_id' => ['spouse_national_id', 'هوية الزوج/ة', 'هوية الزوجة', 'هوية الزوج'],
            'spouse_birth_date' => ['spouse_birth_date', 'تاريخ ميلاد الزوج/ة', 'تاريخ ميلاد الزوجة', 'تاريخ ميلاد الزوج'],
            'governorate' => ['governorate', 'orphan_governorate', 'المحافظة'],
            'area' => ['area', 'orphan_area', 'المنطقة'],
            'family_members_count' => ['family_members_count', 'عدد الافراد', 'عدد الأفراد'],
            'mobile' => ['mobile', 'orphan_mobile', 'جوال اليتيم', 'رقم الجوال'],
            'war_injury' => ['war_injury', 'اصابة حرب', 'إصابة حرب'],
            'chronic_diseases' => ['chronic_diseases', 'orphan_chronic_diseases', 'الأمراض المزمنة لليتيم', 'امراض مزمنة', 'أمراض مزمنة'],
            'disability' => ['disability', 'orphan_disability', 'الإعاقات لليتيم', 'اعاقة', 'إعاقة'],
            'employment_status' => ['employment_status', 'حالة العمل'],
            'notes' => ['notes', 'ملاحظات', 'ملاحظات عامة', 'ملاحظات اليتيم'],
            'head_age' => ['head_age', 'عمر رب الاسرة', 'عمر رب الأسرة'],
            'spouse_age' => ['spouse_age', 'عمر الزوج/ة', 'عمر الزوجة', 'عمر الزوج'],
            'benefits_from_institution' => ['benefits_from_institution', 'الاستفادات من المؤسسة', 'استفادات اليتيم'],
            'guardian_name' => ['guardian_name', 'اسم الوصي'],
            'guardian_national_id' => ['guardian_national_id', 'guardian_id', 'هوية الوصي', 'رقم هوية الوصي'],
            'guardian_mobile' => ['guardian_mobile', 'جوال الوصي'],
            'guardian_relation' => ['guardian_relation', 'صلة القرابة', 'العلاقة مع اليتيم'],
            'siblings_count' => ['siblings_count', 'عدد الإخوة'],
            'father_death_date' => ['father_death_date', 'تاريخ وفاة الأب'],
            'mother_death_date' => ['mother_death_date', 'تاريخ وفاة الأم'],
            'education_status' => ['education_status', 'الحالة الدراسية'],
            'detailed_address' => ['detailed_address', 'عنوان السكن بالتفصيل'],
            'contact_phones' => ['contact_phones', 'أرقام الهاتف/الجوال', 'ارقام الهاتف/الجوال'],
            'institution_id' => ['institution_id'],
            'institution_name' => ['institution_name'],
            'institution' => ['institution', 'المؤسسة'],
            'administrator_id' => ['administrator_id'],
            'data_entry_id' => ['data_entry_id'],
        ];

        foreach ($aliases as $canonical => $names) {
            foreach ($names as $name) {
                if ($this->normalizeHeader($name) === $normalized) {
                    return $canonical;
                }
            }
        }

        return trim(Str::lower($rawHeader));
    }

    private function normalizeHeader(string $value): string
    {
        $value = trim(Str::lower($value));
        $value = str_replace(['_', '-', '/', '\\'], ' ', $value);
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

        return $value;
    }

    private function parseBoolean(mixed $value): bool
    {
        $normalized = trim(Str::lower((string) $value));
        return in_array($normalized, ['1', 'yes', 'true', 'نعم'], true);
    }

    private function toNullableInt(mixed $value, ?int $fallback = null): ?int
    {
        if (is_numeric($value) && (int) $value > 0) {
            return (int) $value;
        }

        return $fallback;
    }

    private function createOrUpdateFamily(array $payload): string
    {
        $institutionId = (int) ($payload['institution_id'] ?? 0);
        $normalizedNationalId = $this->normalizeNationalId((string) ($payload['head_national_id'] ?? ''));

        if ($normalizedNationalId === '' || $institutionId <= 0) {
            Family::create($payload);
            return 'created';
        }

        $matches = Family::query()
            ->where('institution_id', $institutionId)
            ->where('head_national_id', $normalizedNationalId)
            ->orderBy('id')
            ->get();

        if ($matches->count() > 1) {
            throw new \RuntimeException('multiple existing families found for same institution and head_national_id');
        }

        $existing = $matches->first();
        if (! $existing) {
            Family::create($payload);
            return 'created';
        }

        $existing->fill($payload);
        if (! $existing->isDirty()) {
            return 'unchanged';
        }

        $existing->save();
        return 'updated';
    }

    private function normalizeNationalId(string $value): string
    {
        $value = trim($value);
        $value = preg_replace('/\s+/u', '', $value) ?? $value;
        return $value;
    }

    private function normalizeGender(mixed $value): ?string
    {
        $normalized = trim(Str::lower((string) $value));
        if ($normalized === '') {
            return null;
        }
        if (in_array($normalized, ['female', 'f', 'woman', 'انثى', 'أنثى'], true)) {
            return 'female';
        }
        if (in_array($normalized, ['male', 'm', 'man', 'ذكر'], true)) {
            return 'male';
        }
        return null;
    }

    private function normalizeGuardianRelation(mixed $value): ?string
    {
        $normalized = trim(Str::lower((string) $value));
        if ($normalized === '') {
            return null;
        }

        $map = [
            'mother' => ['mother', 'الام', 'الأم', 'ام', 'الام', 'الام '],
            'grandfather' => ['grandfather', 'الجد'],
            'grandmother' => ['grandmother', 'الجدة'],
            'uncle' => ['uncle', 'العم'],
            'maternal_uncle' => ['maternal_uncle', 'maternal uncle', 'الخال'],
            'brother' => ['brother', 'الأخ', 'الاخ'],
            'sister' => ['sister', 'الأخت', 'الاخت'],
            'other' => ['other', 'اخر', 'أخرى'],
        ];

        foreach ($map as $canonical => $aliases) {
            $normalizedAliases = array_map(static fn (string $alias): string => trim(Str::lower($alias)), $aliases);
            if (in_array($normalized, $normalizedAliases, true)) {
                return $canonical;
            }
        }

        return null;
    }
}
