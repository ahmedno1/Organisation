@extends('layouts.app')

@php
    $orphan = $orphan ?? null;
    $isEdit = $orphan !== null;
    $orphanMember = collect($orphan->members_payload ?? [])->first() ?? [];
    $guardianRelation = old('guardian_relation', $orphan?->guardian_relation);
    $guardianRelationOptions = [
        'mother' => 'الأم',
        'grandfather' => 'الجد',
        'grandmother' => 'الجدة',
        'uncle' => 'العم',
        'maternal_uncle' => 'الخال',
        'brother' => 'الأخ',
        'sister' => 'الأخت',
        'other' => 'أخرى',
    ];
@endphp

@section('title', $isEdit ? __('ui.edit_orphan') : __('ui.add_orphan'))

@section('content')
    <div class="card institution-banner">
        <div class="institution-cell">
            @if(optional(auth()->user()->institution)->logo_url)
                <img
                    src="{{ auth()->user()->institution->logo_url }}"
                    alt="{{ __('ui.institution_logo') }}"
                    class="institution-logo"
                >
            @endif
            <div>
                <h3>{{ __('ui.institution') }}</h3>
                <p>{{ auth()->user()->institution?->name ?? __('ui.not_specified') }}</p>
            </div>
        </div>
    </div>

    <div class="card family-form-card">
        <form
            method="POST"
            action="{{ $isEdit ? route('orphans.update', $orphan) : route('orphans.store') }}"
            class="form-grid family-entry-form"
            enctype="multipart/form-data"
            autocomplete="off"
        >
            @csrf
            @if($isEdit)
                @method('PUT')
            @endif

            @if($user->isDeveloper())
                <div class="form-grid two-cols">
                    <label>
                        المؤسسة
                        <select name="institution_id" required>
                            <option value="">-- اختر مؤسسة --</option>
                            @foreach($institutions as $institution)
                                <option
                                    value="{{ $institution->id }}"
                                    @selected((string) old('institution_id', optional($orphan)->institution_id) === (string) $institution->id)
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
                                <option
                                    value="{{ $administrator->id }}"
                                    @selected((string) old('administrator_id', optional($orphan)->administrator_id) === (string) $administrator->id)
                                >
                                    {{ $administrator->name }}
                                </option>
                            @endforeach
                        </select>
                    </label>
                </div>
                <hr>
            @endif

            <h3>بيانات اليتيم</h3>
            <input type="hidden" name="members[0][relation]" value="يتيم">
            <div class="form-grid two-cols">
                <label>
                    اسم اليتيم
                    <input type="text" name="members[0][name]" value="{{ old('members.0.name', $orphanMember['name'] ?? $orphan?->head_name) }}" required>
                </label>
                <label>
                    رقم هوية اليتيم
                    <input type="text" name="members[0][national_id]" value="{{ old('members.0.national_id', $orphanMember['national_id'] ?? $orphan?->head_national_id) }}" required pattern="\d{9}" maxlength="9" inputmode="numeric">
                </label>
                <label>
                    تاريخ ميلاد اليتيم
                    <input type="date" name="members[0][birth_date]" value="{{ old('members.0.birth_date', $orphanMember['birth_date'] ?? optional($orphan?->head_birth_date)->format('Y-m-d')) }}" required>
                </label>
                <label>
                    مكان ميلاد اليتيم
                    <input type="text" name="members[0][birth_place]" value="{{ old('members.0.birth_place', $orphanMember['birth_place'] ?? '') }}" required>
                </label>
                <label>
                    الحالة الصحية لليتيم
                    <input type="text" name="members[0][health_status]" value="{{ old('members.0.health_status', $orphanMember['health_status'] ?? '') }}" required>
                </label>
                <label>
                    جوال اليتيم
                    <input type="text" name="members[0][mobile]" value="{{ old('members.0.mobile', $orphanMember['mobile'] ?? $orphan?->mobile) }}" pattern="\d{10}" maxlength="10" inputmode="numeric">
                </label>
                <label>
                    الجنس
                    <select name="head_gender" required>
                        <option value="">-- اختر --</option>
                        <option value="male" @selected(old('head_gender', $orphan?->head_gender) === 'male')>ذكر</option>
                        <option value="female" @selected(old('head_gender', $orphan?->head_gender) === 'female')>أنثى</option>
                    </select>
                </label>
                <label>
                    المحافظة
                    <select name="governorate">
                        <option value="">-- اختر المحافظة --</option>
                        @foreach($governorateOptions as $governorate)
                            <option value="{{ $governorate }}" @selected(old('governorate', $orphan?->governorate) === $governorate)>{{ $governorate }}</option>
                        @endforeach
                    </select>
                </label>
                <label>
                    المنطقة
                    <input type="text" name="area" value="{{ old('area', $orphan?->area) }}">
                </label>
                <label class="full-width">
                    الأمراض المزمنة لليتيم
                    @php
                        $selectedChronic = old('members.0.chronic_disease_items', collect(preg_split('/[،,]+/u', (string) ($orphan?->chronic_diseases ?? '')) ?: [])->map(fn ($v) => trim((string) $v))->filter()->values()->toArray());
                    @endphp
                    <div class="checkbox-grid" data-member-health-group data-three-options>
                        @foreach($chronicOptions as $option)
                            <label class="checkbox-row">
                                <input type="checkbox" name="members[0][chronic_disease_items][]" value="{{ $option }}" @checked(in_array($option, $selectedChronic, true))>
                                <span>{{ $option }}</span>
                            </label>
                        @endforeach
                    </div>
                </label>
                <label class="full-width">
                    الإعاقات لليتيم
                    @php
                        $selectedDisability = old('members.0.disability_items', collect(preg_split('/[،,]+/u', (string) ($orphan?->disability ?? '')) ?: [])->map(fn ($v) => trim((string) $v))->filter()->values()->toArray());
                    @endphp
                    <div class="checkbox-grid" data-member-health-group data-three-options>
                        @foreach($disabilityOptions as $option)
                            <label class="checkbox-row">
                                <input type="checkbox" name="members[0][disability_items][]" value="{{ $option }}" @checked(in_array($option, $selectedDisability, true))>
                                <span>{{ $option }}</span>
                            </label>
                        @endforeach
                    </div>
                </label>
                <label class="full-width">
                    استفادات اليتيم
                    <div class="checkbox-grid" data-benefit-group data-three-options>
                        @foreach($benefits as $benefit)
                            <label class="checkbox-row">
                                <input type="checkbox" name="members[0][benefit_ids][]" value="{{ $benefit->id }}" @checked(in_array((string) $benefit->id, old('members.0.benefit_ids', []), true))>
                                <span>{{ $benefit->name }} | داعم: {{ $benefit->supporter_name ?: '-' }} | {{ optional($benefit->benefit_date)->format('Y-m-d') ?: '-' }} | {{ $benefit->target_category ?: '-' }}</span>
                            </label>
                        @endforeach
                    </div>
                </label>
            </div>

            <hr>
            <h3>بيانات الوصي (إن وجد)</h3>
            <div class="form-grid two-cols">
                <label>
                    اسم الوصي
                    <input type="text" name="guardian_name" value="{{ old('guardian_name', $orphan?->guardian_name) }}" required>
                </label>
                <label>
                    رقم هوية الوصي
                    <input type="text" name="guardian_national_id" value="{{ old('guardian_national_id', $orphan?->guardian_national_id) }}" pattern="\d{9}" maxlength="9" inputmode="numeric" required>
                </label>
                <label>
                    جوال الوصي
                    <input type="text" name="guardian_mobile" value="{{ old('guardian_mobile', $orphan?->guardian_mobile) }}" pattern="\d{10}" maxlength="10" inputmode="numeric" required>
                </label>
                <label class="full-width">
                    صلة القرابة
                    <div class="checkbox-grid">
                        @foreach($guardianRelationOptions as $value => $label)
                            <label class="checkbox-row">
                                <input type="radio" name="guardian_relation" value="{{ $value }}" @checked($guardianRelation === $value) required>
                                <span>{{ $label }}</span>
                            </label>
                        @endforeach
                    </div>
                </label>
            </div>

            <hr>
            <h3>بيانات إضافية</h3>
            <div class="form-grid two-cols">
                <label>
                    عدد الإخوة
                    <input type="number" name="siblings_count" id="siblings_count" value="{{ old('siblings_count', $orphan?->siblings_count) }}" min="0" max="50" required>
                </label>
                <label>
                    عدد أفراد الأسرة (محسوب)
                    <input type="number" id="orphan_family_members_count" value="{{ max(1, 1 + (int) old('siblings_count', $orphan?->siblings_count ?? 0) + ($guardianRelation === 'mother' ? 1 : 0)) }}" readonly>
                </label>
                <label>
                    تاريخ وفاة الأب
                    <input type="date" name="father_death_date" value="{{ old('father_death_date', optional($orphan?->father_death_date)->format('Y-m-d')) }}" required>
                </label>
                <label>
                    تاريخ وفاة الأم (إن كانت متوفاة)
                    <input type="date" name="mother_death_date" value="{{ old('mother_death_date', optional($orphan?->mother_death_date)->format('Y-m-d')) }}">
                </label>
                <label>
                    الحالة الدراسية
                    <input type="text" name="education_status" value="{{ old('education_status', $orphan?->education_status) }}" required>
                </label>
                <label class="full-width">
                    صورة اليتيم (بالاسم الرباعي)
                    <input type="file" name="orphan_photo" accept="image/*" {{ $isEdit ? '' : 'required' }}>
                </label>
                <label class="full-width">
                    شهادة الوفاة (أوراق ثبوتية)
                    <input type="file" name="death_certificate" accept=".pdf,image/*" {{ $isEdit ? '' : 'required' }}>
                </label>
                <label class="full-width">
                    باقي الأوراق الثبوتية
                    <input type="file" name="supporting_documents[]" multiple accept="image/*,.pdf,.doc,.docx,.xls,.xlsx,.txt">
                </label>
                <label class="full-width">
                    ملاحظات اليتيم
                    <textarea name="members[0][notes]">{{ old('members.0.notes', $orphanMember['notes'] ?? '') }}</textarea>
                </label>
            </div>

            <label class="full-width">
                ملاحظات عامة
                <textarea name="notes">{{ old('notes', $orphan?->notes) }}</textarea>
            </label>

            <div class="form-submit-bar">
                <button class="btn primary" type="submit">{{ $isEdit ? __('ui.update') : 'حفظ سجل اليتيم' }}</button>
                <a class="btn" href="{{ route('orphans.index') }}">إلغاء</a>
            </div>
        </form>
    </div>
@endsection

@push('scripts')
    <script>
        (() => {
            const siblingsInput = document.getElementById('siblings_count');
            const totalInput = document.getElementById('orphan_family_members_count');
            if (!siblingsInput || !totalInput) {
                return;
            }

            const syncFamilyCount = () => {
                const siblings = Math.max(0, Number(siblingsInput.value || 0));
                const relationInput = document.querySelector('input[name="guardian_relation"]:checked');
                const guardianExtra = relationInput?.value === 'mother' ? 1 : 0;
                totalInput.value = String(1 + siblings + guardianExtra);
            };

            siblingsInput.addEventListener('input', syncFamilyCount);
            siblingsInput.addEventListener('change', syncFamilyCount);
            document.querySelectorAll('input[name="guardian_relation"]').forEach((input) => {
                input.addEventListener('change', syncFamilyCount);
            });

            syncFamilyCount();
        })();
    </script>
@endpush
