@php
    $family = $family ?? null;
    $selectedChronicDiseases = old(
        'chronic_disease_items',
        collect(explode('،', (string) optional($family)->chronic_diseases))
            ->map(fn ($item) => trim($item))
            ->filter()
            ->values()
            ->toArray()
    );
    $selectedDisabilities = old(
        'disability_items',
        collect(explode('،', (string) optional($family)->disability))
            ->map(fn ($item) => trim($item))
            ->filter()
            ->values()
            ->toArray()
    );
    $chronicOptions = config('family_options.chronic_diseases', []);
    $chronicOtherOption = 'غير ذلك';
    $disabilityOptions = config('family_options.disabilities', []);
    $disabilityOtherOption = 'غير ذلك';
    $governorateOptions = config('family_options.palestine_governorates', []);

    $selectedBenefitIds = old(
        'benefit_ids',
        $family ? $family->benefits->pluck('id')->map(fn ($id) => (string) $id)->toArray() : []
    );

    $members = old('members', $family ? $family->members->map(function ($member) {
        $memberChronicItems = preg_split('/[،,]+/u', (string) ($member->chronic_diseases ?? '')) ?: [];
        $memberDisabilityItems = preg_split('/[،,]+/u', (string) ($member->disability ?? '')) ?: [];

        return [
            'relation' => $member->relation,
            'name' => $member->name,
            'national_id' => $member->national_id,
            'birth_date' => optional($member->birth_date)->format('Y-m-d'),
            'mobile' => $member->mobile,
            'head_age' => $member->head_age,
            'member_age' => $member->member_age,
            'chronic_disease_items' => collect($memberChronicItems)->map(fn ($item) => trim((string) $item))->filter()->values()->toArray(),
            'disability_items' => collect($memberDisabilityItems)->map(fn ($item) => trim((string) $item))->filter()->values()->toArray(),
            'notes' => $member->notes,
            'benefit_ids' => $member->benefits->pluck('id')->map(fn ($id) => (string) $id)->values()->toArray(),
        ];
    })->toArray() : []);

    $oldSpouseName = old('spouse_name', optional($family)->spouse_name);
    $oldSpouseNationalId = old('spouse_national_id', optional($family)->spouse_national_id);
    $oldSpouseBirthDate = old('spouse_birth_date', optional(optional($family)->spouse_birth_date)->format('Y-m-d'));
    $oldSpousePregnant = old('spouse_pregnant', optional($family)->spouse_pregnant);
    $selectedMaritalStatus = old('marital_status', optional($family)->marital_status);
    $isMarriedStatus = in_array((string) $selectedMaritalStatus, ['متزوج', 'متزوجة'], true);
    $oldGovernorate = old('governorate', optional($family)->governorate);
    if (! empty($oldGovernorate) && ! in_array($oldGovernorate, $governorateOptions, true)) {
        array_unshift($governorateOptions, $oldGovernorate);
    }
    $hasSpouseName = trim((string) $oldSpouseName) !== '';
    $membersWithNames = collect($members)->filter(fn ($member) => ! empty($member['name']))->count();
    $computedFamilyCount = 1 + $membersWithNames + ($hasSpouseName ? 1 : 0);
    $formDraftKey = 'family_form_draft_' . ($family?->id ?? 'new') . '_' . ($user?->id ?? 'guest');
    $formatBenefitLabel = function ($benefit): string {
        $date = optional($benefit->benefit_date)->format('Y-m-d') ?: '-';
        $supporter = trim((string) ($benefit->supporter_name ?? ''));
        $supporter = $supporter !== '' ? $supporter : '-';
        $category = trim((string) ($benefit->target_category ?? ''));
        $category = $category !== '' ? $category : '-';

        return sprintf('%s | داعم: %s | %s | %s', $benefit->name, $supporter, $date, $category);
    };
@endphp

<section class="form-block">
    <div class="form-block-head">
        <h3>البيانات الأساسية للأسرة</h3>
        <p>أدخل المعلومات الرئيسية لرب الأسرة والزوج/ة والحالة الاجتماعية.</p>
    </div>

<div class="form-grid two-cols enhanced-grid">
    @if($user->isDeveloper())
        <label>
            المؤسسة
            <select name="institution_id" required>
                <option value="">-- اختر مؤسسة --</option>
                @foreach($institutions as $institution)
                    <option
                        value="{{ $institution->id }}"
                        data-name="{{ $institution->name }}"
                        data-logo-url="{{ $institution->logo_url ?? '' }}"
                        @selected((string) old('institution_id', optional($family)->institution_id) === (string) $institution->id)
                    >
                        {{ $institution->name }}
                    </option>
                @endforeach
            </select>
        </label>

        <label>
            المسؤول (اختياري)
            <select name="administrator_id">
                <option value="">-- بدون --</option>
                @foreach($administrators as $administrator)
                    <option value="{{ $administrator->id }}" data-institution-id="{{ $administrator->institution_id }}" @selected((string) old('administrator_id', optional($family)->administrator_id) === (string) $administrator->id)>
                        {{ $administrator->name }} - {{ $administrator->institution?->name }}
                    </option>
                @endforeach
            </select>
        </label>
    @endif
    <div class="form-grid-divider"><span>البيانات الشخصية</span></div>


    <label>
        اسم رب الأسرة
        <input type="text" name="head_name" value="{{ old('head_name', optional($family)->head_name) }}" required>
    </label>

    <label>
        الهوية
        <input type="text" name="head_national_id" value="{{ old('head_national_id', optional($family)->head_national_id) }}" required pattern="\d{9}" maxlength="9" inputmode="numeric">
    </label>

    <label>
        جنس رب الأسرة
        <select name="head_gender" required>
            <option value="">-- اختر --</option>
            <option value="male" @selected(old('head_gender', optional($family)->head_gender) === 'male')>ذكر</option>
            <option value="female" @selected(old('head_gender', optional($family)->head_gender) === 'female')>أنثى</option>
        </select>
    </label>

    <label>
        تاريخ ميلاد رب الأسرة
        <input type="date" name="head_birth_date" value="{{ old('head_birth_date', optional(optional($family)->head_birth_date)->format('Y-m-d')) }}" required>
    </label>

    <label>
        الحالة الاجتماعية
        <select name="marital_status" id="marital_status" required>
            <option value="">-- اختر --</option>
            <option value="اعزب" data-gender="male" @selected(old('marital_status', optional($family)->marital_status) === 'اعزب')>اعزب</option>
            <option value="متزوج" data-gender="male" @selected(old('marital_status', optional($family)->marital_status) === 'متزوج')>متزوج</option>
            <option value="ارمل" data-gender="male" @selected(old('marital_status', optional($family)->marital_status) === 'ارمل')>ارمل</option>
            <option value="مطلق" data-gender="male" @selected(old('marital_status', optional($family)->marital_status) === 'مطلق')>مطلق</option>
            <option value="انسة" data-gender="female" @selected(old('marital_status', optional($family)->marital_status) === 'انسة')>انسة</option>
            <option value="متزوجة" data-gender="female" @selected(old('marital_status', optional($family)->marital_status) === 'متزوجة')>متزوجة</option>
            <option value="ارملة" data-gender="female" @selected(old('marital_status', optional($family)->marital_status) === 'ارملة')>ارملة</option>
            <option value="مطلقة" data-gender="female" @selected(old('marital_status', optional($family)->marital_status) === 'مطلقة')>مطلقة</option>
        </select>
    </label>

    <label id="spouse_name_wrapper" @if(! $isMarriedStatus) style="display:none;" @endif>
        اسم الزوج/ة
        <input type="text" name="spouse_name" id="spouse_name" value="{{ $oldSpouseName }}" @if(! $isMarriedStatus) disabled @endif>
    </label>

    <label id="spouse_national_id_wrapper" @if(! $isMarriedStatus) style="display:none;" @endif>
        رقم هوية الزوج/ة
        <input type="text" name="spouse_national_id" id="spouse_national_id" value="{{ $oldSpouseNationalId }}" pattern="\d{9}" maxlength="9" inputmode="numeric" @if(! $isMarriedStatus) disabled @endif>
    </label>

    <label id="spouse_birth_date_wrapper" @if(! $isMarriedStatus) style="display:none;" @endif>
        تاريخ ميلاد الزوج/ة
        <input type="date" name="spouse_birth_date" id="spouse_birth_date" value="{{ $oldSpouseBirthDate }}" @if(! $isMarriedStatus) disabled @endif>
    </label>
    <label id="spouse_pregnant_wrapper" @if(! $isMarriedStatus) style="display:none;" @endif>
        هل الزوجة حامل؟
        <select name="spouse_pregnant" id="spouse_pregnant" @if(! $isMarriedStatus) disabled @endif>
            <option value="">-- اختر --</option>
            <option value="1" @selected((string) $oldSpousePregnant === '1')>نعم</option>
            <option value="0" @selected((string) $oldSpousePregnant === '0')>لا</option>
        </select>
    </label>
    <div class="form-grid-divider"><span>العنوان والتواصل</span></div>


    <label>
        المحافظة
        <select name="governorate">
            <option value="">-- اختر المحافظة --</option>
            @foreach($governorateOptions as $governorate)
                <option value="{{ $governorate }}" @selected($oldGovernorate === $governorate)>{{ $governorate }}</option>
            @endforeach
        </select>
    </label>

    <label>
        المنطقة
        <input type="text" name="area" value="{{ old('area', optional($family)->area) }}">
    </label>

    <label>
        عدد الأفراد
        <input type="number" min="1" name="family_members_count" id="family_members_count" value="{{ old('family_members_count', $computedFamilyCount) }}" readonly>
    </label>

    <label>
        رقم الجوال
        <input type="text" name="mobile" value="{{ old('mobile', optional($family)->mobile) }}" pattern="\d{10}" maxlength="10" inputmode="numeric">
    </label>

    <label>
        إصابة حرب
        <select name="war_injury">
            <option value="0" @selected((string) old('war_injury', (int) (optional($family)->war_injury ?? false)) === '0')>لا</option>
            <option value="1" @selected((string) old('war_injury', (int) (optional($family)->war_injury ?? false)) === '1')>نعم</option>
        </select>
    </label>

    <div class="form-grid-divider"><span>الحالة الصحية</span></div>

    <label>
        قائمة الأمراض المزمنة
        <div class="checkbox-grid compact-checklist" id="chronic_disease_items">
            @foreach($chronicOptions as $option)
                <label class="checkbox-row">
                    <input type="checkbox" name="chronic_disease_items[]" value="{{ $option }}" @checked(in_array($option, $selectedChronicDiseases, true))>
                    <span>{{ $option }}</span>
                </label>
            @endforeach
        </div>
        <small class="field-help" id="chronic_disease_items_help">اختر مرضًا أو أكثر.</small>
    </label>

    <label id="chronic_diseases_extra_wrapper">
        أمراض مزمنة أخرى (اختياري)
        <input class="compact-input" id="chronic_diseases_extra" type="text" name="chronic_diseases_extra" value="{{ old('chronic_diseases_extra') }}">
    </label>

    <label>
        قائمة الإعاقات
        <div class="checkbox-grid compact-checklist" id="disability_items">
            @foreach($disabilityOptions as $option)
                <label class="checkbox-row">
                    <input type="checkbox" name="disability_items[]" value="{{ $option }}" @checked(in_array($option, $selectedDisabilities, true))>
                    <span>{{ $option }}</span>
                </label>
            @endforeach
        </div>
        <small class="field-help" id="disability_items_help">يمكن اختيار أكثر من نوع.</small>
    </label>

    <label id="disability_extra_wrapper">
        إعاقة أخرى (اختياري)
        <input class="compact-input" id="disability_extra" type="text" name="disability_extra" value="{{ old('disability_extra') }}">
    </label>

    <div class="form-grid-divider"><span>بيانات إضافية</span></div>

    <label>
        حالة العمل
        <input type="text" name="employment_status" value="{{ old('employment_status', optional($family)->employment_status) }}">
    </label>

    <label>
        عمر رب الأسرة
        <input type="number" min="0" name="head_age" id="head_age" value="{{ old('head_age', optional($family)->head_age) }}" readonly>
    </label>

    <label id="spouse_age_wrapper" @if(! $isMarriedStatus) style="display:none;" @endif>
        عمر الزوج/ة
        <input type="number" min="0" name="spouse_age" id="spouse_age" value="{{ old('spouse_age', optional($family)->spouse_age) }}" readonly @if(! $isMarriedStatus) disabled @endif>
    </label>

    <label>
        مرفقات الأسرة
        <small class="field-help">يمكنك رفع صور أو ملفات للأسرة الجديدة أثناء الإضافة.</small>
        <input type="file" name="family_attachments[]" multiple accept="image/*,.pdf,.doc,.docx,.xls,.xlsx,.txt">
    </label>
</div>
</section>

<section class="form-block">
    <div class="form-block-head">
        <h3>الاستفادات من المؤسسة</h3>
        <p>حدد الاستفادات الحالية وأضف استفادات جديدة عند الحاجة.</p>
    </div>
<label>
    قائمة الاستفادات من المؤسسة
    <div class="checkbox-grid" id="benefit_ids" data-benefit-group>
        @foreach($benefits as $benefit)
            <label class="checkbox-row">
                <input type="checkbox" name="benefit_ids[]" value="{{ $benefit->id }}" @checked(in_array((string) $benefit->id, $selectedBenefitIds, true))>
                <span>{{ $formatBenefitLabel($benefit) }}</span>
            </label>
        @endforeach
    </div>
    <small class="field-help" id="benefit_ids_help">اختر الاستفادات المتاحة في النظام.</small>
</label>

@if($family)
    <label>
        الاستفادات من المؤسسة لكل أسرة
        <textarea name="benefits_from_institution">{{ old('benefits_from_institution', optional($family)->benefits_from_institution) }}</textarea>
    </label>
@endif

<label>
    ملاحظات
    <textarea name="notes">{{ old('notes', optional($family)->notes) }}</textarea>
</label>
</section>

<section class="form-block">
    <div class="section-head form-section-head mt-16">
        <h3>أفراد الأسرة</h3>
        <button class="btn" type="button" id="add-member-btn">إضافة فرد</button>
    </div>

    @php
        $renderMembers = collect($members);
        if ($renderMembers->isEmpty()) {
            $renderMembers = collect([[]]);
        }
    @endphp
    <div id="members-wrapper" class="members-grid">
        @foreach($renderMembers as $index => $member)
            <div class="member-item" data-member-item>
                <div class="form-grid two-cols">
                    <label>الصفة
                        <select name="members[{{ $index }}][relation]" data-key="relation">
                            <option value="">-- اختر --</option>
                            @foreach(['ابن', 'ابنة', 'اخ', 'اخت', 'حفيد', 'حفيدة'] as $relation)
                                <option value="{{ $relation }}" @selected(($member['relation'] ?? '') === $relation)>{{ $relation }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label>الاسم<input type="text" name="members[{{ $index }}][name]" value="{{ $member['name'] ?? '' }}" data-key="name"></label>
                    <label>الهوية<input type="text" name="members[{{ $index }}][national_id]" value="{{ $member['national_id'] ?? '' }}" data-key="national_id"></label>
                    <label>تاريخ الميلاد<input type="date" name="members[{{ $index }}][birth_date]" value="{{ $member['birth_date'] ?? '' }}" data-key="birth_date"></label>
                    <label>الجوال<input type="text" name="members[{{ $index }}][mobile]" value="{{ $member['mobile'] ?? '' }}" data-key="mobile" autocomplete="off" pattern="\d{10}" maxlength="10" inputmode="numeric"></label>
                    <label>عمر الفرد<input type="number" min="0" name="members[{{ $index }}][member_age]" value="{{ $member['member_age'] ?? '' }}" data-key="member_age"></label>
                    <label>استفادات الفرد
                        @php $memberBenefitIds = collect($member['benefit_ids'] ?? [])->map(fn($id) => (string) $id)->toArray(); @endphp
                        <div class="checkbox-grid" data-key="benefit_ids" data-checkbox-group>
                            @foreach($benefits as $benefit)
                                <label class="checkbox-row">
                                    <input
                                        type="checkbox"
                                        name="members[{{ $index }}][benefit_ids][]"
                                        value="{{ $benefit->id }}"
                                        @checked(in_array((string) $benefit->id, $memberBenefitIds, true))
                                    >
                                    <span>{{ $formatBenefitLabel($benefit) }}</span>
                                </label>
                            @endforeach
                        </div>
                    </label>
                    <label>قائمة الأمراض المزمنة للفرد
                        @php $memberChronicItems = collect($member['chronic_disease_items'] ?? [])->map(fn($item) => trim((string) $item))->filter()->values()->toArray(); @endphp
                        <div class="checkbox-grid" data-key="chronic_disease_items" data-checkbox-group data-member-health-group>
                            @foreach($chronicOptions as $option)
                                <label class="checkbox-row">
                                    <input
                                        type="checkbox"
                                        name="members[{{ $index }}][chronic_disease_items][]"
                                        value="{{ $option }}"
                                        @checked(in_array($option, $memberChronicItems, true))
                                    >
                                    <span>{{ $option }}</span>
                                </label>
                            @endforeach
                        </div>
                    </label>
                    <label>قائمة الإعاقات للفرد
                        @php $memberDisabilityItems = collect($member['disability_items'] ?? [])->map(fn($item) => trim((string) $item))->filter()->values()->toArray(); @endphp
                        <div class="checkbox-grid" data-key="disability_items" data-checkbox-group data-member-health-group>
                            @foreach($disabilityOptions as $option)
                                <label class="checkbox-row">
                                    <input
                                        type="checkbox"
                                        name="members[{{ $index }}][disability_items][]"
                                        value="{{ $option }}"
                                        @checked(in_array($option, $memberDisabilityItems, true))
                                    >
                                    <span>{{ $option }}</span>
                                </label>
                            @endforeach
                        </div>
                    </label>
                    <label>مرفقات الفرد
                        <input type="file" name="members[{{ $index }}][attachments][]" data-key="attachments" multiple accept="image/*,.pdf,.doc,.docx,.xls,.xlsx,.txt">
                    </label>
                    <label>ملاحظات<textarea name="members[{{ $index }}][notes]" data-key="notes">{{ $member['notes'] ?? '' }}</textarea></label>
                </div>
                <button class="btn danger mt-8" type="button" data-remove-member>حذف الفرد</button>
            </div>
        @endforeach
    </div>
</section>

<template id="member-template">
    <div class="member-item">
        <div class="form-grid two-cols">
            <label>الصفة
                <select data-key="relation">
                    <option value="">-- اختر --</option>
                    <option value="ابن">ابن</option>
                    <option value="ابنة">ابنة</option>
                    <option value="اخ">اخ</option>
                    <option value="اخت">اخت</option>
                    <option value="حفيد">حفيد</option>
                    <option value="حفيدة">حفيدة</option>
                </select>
            </label>
            <label>الاسم<input type="text" data-key="name"></label>
            <label>الهوية<input type="text" data-key="national_id"></label>
            <label>تاريخ الميلاد<input type="date" data-key="birth_date"></label>
            <label>الجوال<input type="text" data-key="mobile" autocomplete="off" pattern="\d{10}" maxlength="10" inputmode="numeric"></label>
            <label>عمر الفرد<input type="number" min="0" data-key="member_age"></label>
            <label>استفادات الفرد
                <div class="checkbox-grid" data-key="benefit_ids" data-checkbox-group>
                    @foreach($benefits as $benefit)
                        <label class="checkbox-row">
                            <input type="checkbox" value="{{ $benefit->id }}">
                            <span>{{ $formatBenefitLabel($benefit) }}</span>
                        </label>
                    @endforeach
                </div>
            </label>
            <label>قائمة الأمراض المزمنة للفرد
                <div class="checkbox-grid" data-key="chronic_disease_items" data-checkbox-group data-member-health-group>
                    @foreach($chronicOptions as $option)
                        <label class="checkbox-row">
                            <input type="checkbox" value="{{ $option }}">
                            <span>{{ $option }}</span>
                        </label>
                    @endforeach
                </div>
            </label>
            <label>قائمة الإعاقات للفرد
                <div class="checkbox-grid" data-key="disability_items" data-checkbox-group data-member-health-group>
                    @foreach($disabilityOptions as $option)
                        <label class="checkbox-row">
                            <input type="checkbox" value="{{ $option }}">
                            <span>{{ $option }}</span>
                        </label>
                    @endforeach
                </div>
            </label>
            <label>مرفقات الفرد
                <input type="file" data-key="attachments" multiple accept="image/*,.pdf,.doc,.docx,.xls,.xlsx,.txt">
            </label>
            <label>ملاحظات<textarea data-key="notes"></textarea></label>
        </div>
        <button class="btn danger mt-8" type="button" data-remove-member>حذف الفرد</button>
    </div>
</template>

<div class="form-submit-bar">
    <button class="btn primary" type="submit">{{ $submitLabel }}</button>
    <button class="btn" type="button" id="offline-sync-now-btn">مزامنة الآن</button>
    <button class="btn" type="button" id="offline-export-queue-btn">تصدير الطابور</button>
    <button class="btn" type="button" id="offline-import-queue-btn">استيراد الطابور</button>
    <input type="file" id="offline-import-file" accept="application/json" hidden>
</div>
<small class="field-help" id="offline-queue-status"></small>
<small class="field-help" id="offline-archive-status"></small>

@push('scripts')
<script>
    (() => {
        const isEnglishLocale = document.documentElement.lang === 'en';
        const textMap = {
            'البيانات الأساسية للأسرة': 'Family Basic Data',
            'أدخل المعلومات الرئيسية لرب الأسرة والزوج/ة والحالة الاجتماعية.': 'Enter the main details for the family head, spouse, and marital status.',
            'المؤسسة': 'Institution',
            '-- اختر مؤسسة --': '-- Select Institution --',
            'المسؤول (اختياري)': 'Administrator (Optional)',
            '-- بدون --': '-- None --',
            'البيانات الشخصية': 'Personal Data',
            'اسم رب الأسرة': 'Family Head Name',
            'الهوية': 'ID',
            'جنس رب الأسرة': 'Head Gender',
            '-- اختر --': '-- Select --',
            'ذكر': 'Male',
            'أنثى': 'Female',
            'تاريخ ميلاد رب الأسرة': 'Head Birth Date',
            'الحالة الاجتماعية': 'Marital Status',
            'اسم الزوج/ة': 'Spouse Name',
            'رقم هوية الزوج/ة': 'Spouse ID Number',
            'تاريخ ميلاد الزوج/ة': 'Spouse Birth Date',
            'العنوان والتواصل': 'Address and Contact',
            'المحافظة': 'Governorate',
            '-- اختر المحافظة --': '-- Select Governorate --',
            'المنطقة': 'Area',
            'عدد الأفراد': 'Family Members Count',
            'رقم الجوال': 'Mobile',
            'إصابة حرب': 'War Injury',
            'لا': 'No',
            'نعم': 'Yes',
            'الحالة الصحية': 'Health Status',
            'قائمة الأمراض المزمنة': 'Chronic Diseases List',
            'اختر مرضًا أو أكثر.': 'Select one or more diseases.',
            'أمراض مزمنة أخرى (اختياري)': 'Other Chronic Diseases (Optional)',
            'قائمة الإعاقات': 'Disability List',
            'يمكن اختيار أكثر من نوع.': 'You can select more than one type.',
            'إعاقة أخرى (اختياري)': 'Other Disability (Optional)',
            'بيانات إضافية': 'Additional Data',
            'حالة العمل': 'Employment Status',
            'عمر رب الأسرة': 'Head Age',
            'عمر الزوج/ة': 'Spouse Age',
            'الاستفادات من المؤسسة': 'Institution Benefits',
            'حدد الاستفادات الحالية وأضف استفادات جديدة عند الحاجة.': 'Select current benefits and add new ones when needed.',
            'قائمة الاستفادات من المؤسسة': 'Benefits List',
            'اختر الاستفادات المتاحة في النظام.': 'Select available benefits from the system.',
            'إضافة استفادة جديدة': 'Add New Benefit',
            'إضافة خانة': 'Add Field',
            'مزامنة الآن': 'Sync Now',
            'تصدير الطابور': 'Export Queue',
            'استيراد الطابور': 'Import Queue',
            'الاستفادات من المؤسسة لكل أسرة': 'Benefits per Family',
            'ملاحظات': 'Notes',
            'أفراد الأسرة': 'Family Members',
            'إضافة فرد': 'Add Member',
            'الصفة': 'Relation',
            'الاسم': 'Name',
            'تاريخ الميلاد': 'Birth Date',
            'الجوال': 'Mobile',
            'عمر الفرد': 'Member Age',
            'استفادات الفرد': 'Member Benefits',
            'حذف الفرد': 'Delete Member',
            'اكتب اسم الاستفادة الجديدة': 'Type new benefit name',
            'حذف': 'Delete',
            'حفظ': 'Save',
        };

        function localizeStaticTexts() {
            if (!isEnglishLocale) {
                return;
            }

            document.querySelectorAll('label, h2, h3, p, small, button, option, span').forEach((el) => {
                const text = (el.textContent || '').trim();
                if (textMap[text]) {
                    el.textContent = textMap[text];
                }
            });

            document.querySelectorAll('input[placeholder], textarea[placeholder]').forEach((el) => {
                const placeholder = el.getAttribute('placeholder') || '';
                if (textMap[placeholder]) {
                    el.setAttribute('placeholder', textMap[placeholder]);
                }
            });
        }

        localizeStaticTexts();

        const headBirthDateInput = document.querySelector('input[name="head_birth_date"]');
        const headGenderInput = document.querySelector('select[name="head_gender"]');
        const maritalStatusInput = document.getElementById('marital_status');
        const institutionSelect = document.querySelector('select[name="institution_id"]');
        const administratorSelect = document.querySelector('select[name="administrator_id"]');
        const spouseBirthDateInput = document.getElementById('spouse_birth_date');
        const headAgeInput = document.getElementById('head_age');
        const spouseAgeInput = document.getElementById('spouse_age');
        const spouseAgeWrapper = document.getElementById('spouse_age_wrapper');
        const headMobileInput = document.querySelector('input[name="mobile"]');
        const spouseNameInput = document.getElementById('spouse_name');
        const spouseNationalIdInput = document.getElementById('spouse_national_id');
        const spousePregnantInput = document.getElementById('spouse_pregnant');
        const spouseNameWrapper = document.getElementById('spouse_name_wrapper');
        const spouseNationalIdWrapper = document.getElementById('spouse_national_id_wrapper');
        const spouseBirthDateWrapper = document.getElementById('spouse_birth_date_wrapper');
        const spousePregnantWrapper = document.getElementById('spouse_pregnant_wrapper');
        const familyCountInput = document.getElementById('family_members_count');
        const benefitSelect = document.getElementById('benefit_ids');
        const chronicSelect = document.getElementById('chronic_disease_items');
        const chronicExtraWrapper = document.getElementById('chronic_diseases_extra_wrapper');
        const chronicExtraInput = document.getElementById('chronic_diseases_extra');
        const disabilitySelect = document.getElementById('disability_items');
        const disabilityExtraWrapper = document.getElementById('disability_extra_wrapper');
        const disabilityExtraInput = document.getElementById('disability_extra');
        const chronicOtherOption = @json($chronicOtherOption);
        const disabilityOtherOption = @json($disabilityOtherOption);
        const draftKey = @json($formDraftKey);
        const isEditMode = @json((bool) $family);
        const formEl = document.querySelector('.family-entry-form');
        const offlineQueueStatusEl = document.getElementById('offline-queue-status');
        const offlineArchiveStatusEl = document.getElementById('offline-archive-status');
        const offlineSyncNowBtn = document.getElementById('offline-sync-now-btn');
        const offlineExportQueueBtn = document.getElementById('offline-export-queue-btn');
        const offlineImportQueueBtn = document.getElementById('offline-import-queue-btn');
        const offlineImportFileInput = document.getElementById('offline-import-file');
        const offlineQueueManager = window.EsnadOfflineQueue ?? null;

        function renderOfflineQueueStatus() {
            if (!offlineQueueStatusEl || isEditMode || !offlineQueueManager) {
                return;
            }

            const queueCount = offlineQueueManager.getQueueCount();
            const isSyncing = offlineQueueManager.isSyncing();
            const lastError = offlineQueueManager.getLastQueueError();

            if (offlineSyncNowBtn) {
                offlineSyncNowBtn.disabled = !navigator.onLine || isSyncing || queueCount === 0;
            }
            if (offlineExportQueueBtn) {
                offlineExportQueueBtn.disabled = queueCount === 0;
            }

            if (isSyncing) {
                offlineQueueStatusEl.textContent = isEnglishLocale
                    ? `Syncing records... (remaining: ${queueCount})`
                    : `جاري مزامنة السجلات... (المتبقي: ${queueCount})`;
            } else if (queueCount === 0) {
                offlineQueueStatusEl.textContent = navigator.onLine
                    ? (isEnglishLocale ? 'No pending records for sync.' : 'لا توجد سجلات منتظرة للمزامنة.')
                    : (isEnglishLocale ? 'You are offline now. New records will be stored locally.' : 'أنت غير متصل الآن. سيتم حفظ أي سجل جديد محليًا.');
            } else {
                offlineQueueStatusEl.textContent = navigator.onLine
                    ? (isEnglishLocale ? `Pending records for sync: ${queueCount}` : `عدد السجلات المنتظرة للمزامنة: ${queueCount}`)
                    : (isEnglishLocale ? `Offline: ${queueCount} record(s) waiting for sync` : `غير متصل: ${queueCount} سجل(ات) بانتظار المزامنة`);
            }

            if (lastError?.message && queueCount > 0) {
                offlineQueueStatusEl.textContent += isEnglishLocale
                    ? ` | Last error: ${lastError.message}`
                    : ` | آخر خطأ: ${lastError.message}`;
            }

            if (offlineArchiveStatusEl) {
                const archiveCount = offlineQueueManager.getArchiveCount();
                offlineArchiveStatusEl.textContent = isEnglishLocale
                    ? `Local archive for uploaded records: ${archiveCount}`
                    : `الأرشيف المحلي للسجلات المرفوعة: ${archiveCount}`;
            }
        }

        function showOfflineSyncMessage(message, type = 'success') {
            const container = document.querySelector('main.main-content');
            if (!container) {
                return;
            }

            let alertBox = document.getElementById('offline-sync-alert');
            if (!alertBox) {
                alertBox = document.createElement('div');
                alertBox.id = 'offline-sync-alert';
                container.insertBefore(alertBox, container.firstChild);
            }

            alertBox.className = `alert ${type === 'error' ? 'danger' : 'success'}`;
            alertBox.textContent = message;
        }

        function calculateAge(dateStr) {
            if (!dateStr) return '';
            const birthDate = new Date(dateStr);
            if (Number.isNaN(birthDate.getTime())) return '';

            const today = new Date();
            let age = today.getFullYear() - birthDate.getFullYear();
            const monthDiff = today.getMonth() - birthDate.getMonth();
            if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthDate.getDate())) {
                age--;
            }

            return age >= 0 ? age : '';
        }

        function syncParentAges() {
            headAgeInput.value = calculateAge(headBirthDateInput.value);
            spouseAgeInput.value = spouseBirthDateInput?.value ? calculateAge(spouseBirthDateInput.value) : '';
        }

        function hasSpouseName() {
            return (spouseNameInput?.value || '').trim() !== '';
        }

        function countNamedMembers() {
            return Array.from(document.querySelectorAll('#members-wrapper [data-key="name"]'))
                .filter((input) => input.value.trim() !== '')
                .length;
        }

        function syncFamilyCount() {
            if (!familyCountInput) {
                return;
            }

            const total = 1 + countNamedMembers() + (hasSpouseName() ? 1 : 0);
            familyCountInput.value = total;
        }

        function syncMembersMobileWithHead() {
            const headMobile = headMobileInput?.value ?? '';
            document.querySelectorAll('#members-wrapper [data-key="mobile"]').forEach((input) => {
                if (!input.value.trim() || input.dataset.autofilled === '1') {
                    input.value = headMobile;
                    input.dataset.autofilled = headMobile ? '1' : '0';
                }
            });
        }

        function bindCheckboxStatus(wrapperEl, helpElId, baseText) {
            if (!wrapperEl) return;
            const help = document.getElementById(helpElId);
            const render = () => {
                const count = wrapperEl.querySelectorAll('input[type="checkbox"]:checked').length;
                help.textContent = count > 0
                    ? (isEnglishLocale ? `${baseText} (${count} selected)` : `${baseText} (${count} محدد)`)
                    : baseText;
            };
            wrapperEl.addEventListener('change', render);
            render();
        }

        function collectDraftData() {
            const data = {};
            document.querySelectorAll('input[name], select[name], textarea[name]').forEach((el) => {
                if (el.type === 'checkbox') {
                    if (!Array.isArray(data[el.name])) {
                        data[el.name] = [];
                    }
                    if (el.checked) {
                        data[el.name].push(el.value);
                    }
                    return;
                }

                data[el.name] = el.value;
            });

            return data;
        }

        function saveDraft() {
            if (isEditMode) return;
            localStorage.setItem(draftKey, JSON.stringify(collectDraftData()));
        }

        function restoreDraft() {
            if (isEditMode) return;
            const raw = localStorage.getItem(draftKey);
            if (!raw) return;

            try {
                const data = JSON.parse(raw);
                Object.keys(data).forEach((name) => {
                    const elements = document.querySelectorAll(`[name="${name}"]`);
                    if (!elements.length) return;
                    const first = elements[0];
                    if (first.type === 'checkbox') {
                        const values = Array.isArray(data[name]) ? data[name] : [];
                        elements.forEach((el) => {
                            el.checked = values.includes(el.value);
                        });
                        return;
                    }

                    first.value = data[name] ?? '';
                });
            } catch (e) {
                localStorage.removeItem(draftKey);
            }
        }

        function toggleChronicExtraField() {
            if (!chronicSelect || !chronicExtraWrapper || !chronicExtraInput) {
                return;
            }

            const isOtherSelected = Array.from(
                chronicSelect.querySelectorAll('input[type="checkbox"]:checked')
            ).some((input) => input.value.trim() === chronicOtherOption);

            chronicExtraWrapper.style.display = isOtherSelected ? '' : 'none';
            chronicExtraInput.disabled = !isOtherSelected;
        }

        function toggleDisabilityExtraField() {
            if (!disabilitySelect || !disabilityExtraWrapper || !disabilityExtraInput) {
                return;
            }

            const isOtherSelected = Array.from(
                disabilitySelect.querySelectorAll('input[type="checkbox"]:checked')
            ).some((input) => input.value.trim() === disabilityOtherOption);

            disabilityExtraWrapper.style.display = isOtherSelected ? '' : 'none';
            disabilityExtraInput.disabled = !isOtherSelected;
        }

        function filterMaritalStatusByGender() {
            if (!headGenderInput || !maritalStatusInput) {
                return;
            }

            const gender = (headGenderInput.value || '').trim();
            const options = Array.from(maritalStatusInput.querySelectorAll('option'));
            let selectedStillValid = false;

            options.forEach((option) => {
                const optionGender = option.dataset.gender;
                if (!optionGender) {
                    option.hidden = false;
                    option.disabled = false;
                    if (option.selected) {
                        selectedStillValid = true;
                    }
                    return;
                }

                const visible = !gender || optionGender === gender;
                option.hidden = !visible;
                option.disabled = !visible;

                if (visible && option.selected) {
                    selectedStillValid = true;
                }
            });

            if (!selectedStillValid) {
                maritalStatusInput.value = '';
            }
        }

        function filterAdministratorsByInstitution() {
            if (!institutionSelect || !administratorSelect) {
                return;
            }

            const institutionId = (institutionSelect.value || '').trim();
            let selectedStillValid = false;

            Array.from(administratorSelect.options).forEach((option) => {
                const optionInstitutionId = (option.dataset.institutionId || '').trim();
                const isPlaceholder = option.value === '';
                const isVisible = isPlaceholder || !institutionId || optionInstitutionId === institutionId;

                option.hidden = !isVisible;
                option.disabled = !isVisible;

                if (isVisible && option.selected) {
                    selectedStillValid = true;
                }
            });

            if (!selectedStillValid) {
                administratorSelect.value = '';
            }
        }

        function toggleSpouseFieldsByMaritalStatus() {
            if (!maritalStatusInput || !spouseNameInput || !spouseNationalIdInput || !spouseBirthDateInput || !spousePregnantInput) {
                return;
            }

            const isMarried = ['متزوج', 'متزوجة'].includes((maritalStatusInput.value || '').trim());
            [spouseNameWrapper, spouseNationalIdWrapper, spouseBirthDateWrapper, spousePregnantWrapper].forEach((wrapperEl) => {
                if (wrapperEl) {
                    wrapperEl.style.display = isMarried ? '' : 'none';
                }
            });
            if (spouseAgeWrapper) {
                spouseAgeWrapper.style.display = isMarried ? '' : 'none';
            }

            spouseNameInput.required = isMarried;
            spouseNationalIdInput.required = isMarried;
            spouseBirthDateInput.required = isMarried;
            spousePregnantInput.required = isMarried;
            spouseNameInput.disabled = !isMarried;
            spouseNationalIdInput.disabled = !isMarried;
            spouseBirthDateInput.disabled = !isMarried;
            spousePregnantInput.disabled = !isMarried;
            spouseAgeInput.disabled = !isMarried;

            if (!isMarried) {
                spouseNameInput.value = '';
                spouseNationalIdInput.value = '';
                spouseBirthDateInput.value = '';
                spousePregnantInput.value = '';
                spouseAgeInput.value = '';
            } else {
                spouseAgeInput.value = spouseBirthDateInput?.value ? calculateAge(spouseBirthDateInput.value) : '';
            }
        }

        const wrapper = document.getElementById('members-wrapper');
        const template = document.getElementById('member-template');
        const addBtn = document.getElementById('add-member-btn');

        let memberCounter = wrapper?.querySelectorAll('.member-item').length ?? 0;

        function bindMemberItem(item) {
            const memberBirthDateInput = item.querySelector('[data-key="birth_date"]');
            const memberAgeInput = item.querySelector('[data-key="member_age"]');
            const memberMobileInput = item.querySelector('[data-key="mobile"]');

            if (memberAgeInput) {
                memberAgeInput.readOnly = true;
            }
            if (memberMobileInput && !memberMobileInput.value.trim()) {
                memberMobileInput.value = headMobileInput?.value ?? '';
                memberMobileInput.dataset.autofilled = memberMobileInput.value ? '1' : '0';
            }
            memberMobileInput?.addEventListener('input', () => {
                memberMobileInput.dataset.autofilled = '0';
            });

            const updateMemberAge = () => {
                if (memberAgeInput && memberBirthDateInput) {
                    memberAgeInput.value = calculateAge(memberBirthDateInput.value);
                }
            };

            memberBirthDateInput?.addEventListener('change', updateMemberAge);
            updateMemberAge();

            item.querySelector('[data-remove-member]')?.addEventListener('click', () => {
                item.remove();
                syncFamilyCount();
            });
        }

        function renderMember(data = {}) {
            const index = memberCounter++;
            const fragment = template.content.cloneNode(true);
            const item = fragment.querySelector('.member-item');

            item.querySelectorAll('[data-key]').forEach((input) => {
                const key = input.getAttribute('data-key');
                if (input.dataset.checkboxGroup !== undefined) {
                    const selectedValues = Array.isArray(data[key])
                        ? data[key].map((value) => String(value))
                        : [];
                    input.querySelectorAll('input[type="checkbox"]').forEach((checkbox) => {
                        checkbox.name = `members[${index}][${key}][]`;
                        checkbox.checked = selectedValues.includes(String(checkbox.value));
                    });
                    return;
                }

                if (input.type === 'file') {
                    input.name = `members[${index}][${key}][]`;
                    return;
                }

                input.name = `members[${index}][${key}]`;
                input.value = data[key] ?? '';
            });

            bindMemberItem(item);

            wrapper.appendChild(fragment);
            syncFamilyCount();
        }

        wrapper?.querySelectorAll('.member-item').forEach((item) => bindMemberItem(item));
        addBtn?.addEventListener('click', () => renderMember());

        if ((wrapper?.querySelectorAll('.member-item').length ?? 0) === 0) {
            renderMember();
        }

        headBirthDateInput.addEventListener('change', syncParentAges);
        spouseBirthDateInput.addEventListener('change', syncParentAges);
        headMobileInput?.addEventListener('input', syncMembersMobileWithHead);
        headMobileInput?.addEventListener('change', syncMembersMobileWithHead);
        syncParentAges();
        [spouseNameInput, spouseNationalIdInput, spouseBirthDateInput].forEach((input) => {
            if (!input) return;
            input.addEventListener('input', syncFamilyCount);
            input.addEventListener('change', syncFamilyCount);
        });
        wrapper.addEventListener('input', (event) => {
            if (event.target?.matches?.('[data-key="name"]')) {
                syncFamilyCount();
            }
        });
        syncFamilyCount();
        syncMembersMobileWithHead();

        bindCheckboxStatus(benefitSelect, 'benefit_ids_help', isEnglishLocale ? 'Select available benefits from system.' : 'اختر الاستفادات المتاحة في النظام.');
        bindCheckboxStatus(chronicSelect, 'chronic_disease_items_help', isEnglishLocale ? 'Select one or more diseases.' : 'اختر مرضًا أو أكثر.');
        bindCheckboxStatus(disabilitySelect, 'disability_items_help', isEnglishLocale ? 'You can select more than one type.' : 'يمكن اختيار أكثر من نوع.');
        restoreDraft();
        chronicSelect?.addEventListener('change', toggleChronicExtraField);
        disabilitySelect?.addEventListener('change', toggleDisabilityExtraField);
        institutionSelect?.addEventListener('change', filterAdministratorsByInstitution);
        headGenderInput?.addEventListener('change', filterMaritalStatusByGender);
        maritalStatusInput?.addEventListener('change', toggleSpouseFieldsByMaritalStatus);
        toggleChronicExtraField();
        toggleDisabilityExtraField();
        filterAdministratorsByInstitution();
        filterMaritalStatusByGender();
        toggleSpouseFieldsByMaritalStatus();
        document.querySelectorAll('input[name], select[name], textarea[name]').forEach((el) => {
            el.addEventListener('change', saveDraft);
            el.addEventListener('input', saveDraft);
        });

        formEl?.addEventListener('submit', async (event) => {
            if (event.submitter?.name === 'save_benefit') {
                return;
            }

            if (!isEditMode && !navigator.onLine && offlineQueueManager) {
                event.preventDefault();

                const queueResult = await offlineQueueManager.enqueueFormSubmission(formEl);
                if (queueResult?.ok) {
                    localStorage.removeItem(draftKey);
                    showOfflineSyncMessage(isEnglishLocale ? 'Family data saved locally and will upload automatically when online.' : 'تم حفظ بيانات الأسرة محليًا، وسيتم رفعها تلقائيًا عند عودة الإنترنت.');
                } else if (queueResult?.reason === 'duplicate_family') {
                    showOfflineSyncMessage(isEnglishLocale ? 'This record already exists in local queue and will not be duplicated.' : 'هذا السجل موجود مسبقًا في الطابور المحلي ولن يتم تكراره.', 'error');
                } else {
                    showOfflineSyncMessage(isEnglishLocale ? 'Failed to save data locally. Try again.' : 'تعذر حفظ البيانات محليًا. حاول مرة أخرى.', 'error');
                }

                return;
            }

            localStorage.removeItem(draftKey);
        });

        window.addEventListener('online', () => {
            renderOfflineQueueStatus();
            offlineQueueManager?.syncQueue();
        });

        window.addEventListener('offline', () => {
            renderOfflineQueueStatus();
        });

        offlineSyncNowBtn?.addEventListener('click', () => {
            offlineQueueManager?.syncQueue({ manual: true });
        });

        offlineExportQueueBtn?.addEventListener('click', () => {
            if (!offlineQueueManager) {
                return;
            }

            const content = offlineQueueManager.exportQueue();
            const blob = new Blob([content], { type: 'application/json;charset=utf-8' });
            const url = URL.createObjectURL(blob);
            const link = document.createElement('a');
            link.href = url;
            link.download = `family_offline_queue_${new Date().toISOString().slice(0, 19).replace(/[:T]/g, '-')}.json`;
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            URL.revokeObjectURL(url);
        });

        offlineImportQueueBtn?.addEventListener('click', () => {
            offlineImportFileInput?.click();
        });

        offlineImportFileInput?.addEventListener('change', async (event) => {
            const file = event.target.files?.[0];
            event.target.value = '';
            if (!file || !offlineQueueManager) {
                return;
            }

            try {
                const text = await file.text();
                const result = offlineQueueManager.importQueue(text);
                showOfflineSyncMessage(isEnglishLocale ? `Imported ${result.added} record(s) into queue.` : `تم استيراد ${result.added} سجل(ات) إلى الطابور.`);
                renderOfflineQueueStatus();
            } catch (error) {
                showOfflineSyncMessage(isEnglishLocale ? 'Failed to import queue file. Ensure JSON file is valid.' : 'فشل استيراد ملف الطابور. تأكد أن الملف JSON صحيح.', 'error');
            }
        });

        window.addEventListener('esnad-offline-queue-changed', () => {
            renderOfflineQueueStatus();
        });

        window.addEventListener('esnad-offline-sync-finished', (event) => {
            const detail = event.detail ?? {};
            if (detail.lastError?.message) {
                showOfflineSyncMessage(detail.lastError.message, 'error');
            } else if ((detail.synced ?? 0) > 0) {
                showOfflineSyncMessage(isEnglishLocale ? `Successfully synced ${detail.synced} family record(s).` : `تمت مزامنة ${detail.synced} سجل(ات) أسرة بنجاح.`);
            }
            renderOfflineQueueStatus();
        });

        renderOfflineQueueStatus();

        if (!isEditMode && navigator.onLine && offlineQueueManager) {
            offlineQueueManager.syncQueue();
        }
    })();
</script>
@endpush



