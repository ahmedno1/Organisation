@extends('layouts.app')

@section('title', 'بحث أفراد الأسر')
@section('hide_page_head', '1')

@section('content')
    <div class="section-head">
        <h2>بحث أفراد الأسر</h2>
        <div class="actions">
            <a class="btn" href="{{ route('families.index') }}">العودة لبيانات الأسر</a>
        </div>
    </div>

    <div class="card mt-16 toolbar-panel">
        <form method="GET" action="{{ route('families.members.search') }}" class="form-grid two-cols">
            <label>بحث
                <input type="text" name="member_q" value="{{ request('member_q') }}" placeholder="اسم الفرد / الهوية / الجوال">
            </label>
            <label>عمر الفرد من
                <input type="number" name="member_age_from" min="0" value="{{ request('member_age_from') }}">
            </label>
            <label>عمر الفرد إلى
                <input type="number" name="member_age_to" min="0" value="{{ request('member_age_to') }}">
            </label>
            <div class="actions">
                <button class="btn primary" type="submit">بحث</button>
                <a class="btn" href="{{ route('families.members.search') }}">إعادة ضبط</a>
            </div>
        </form>
    </div>

    <div class="card mt-16 desktop-only table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th>اسم رب الأسرة</th>
                    <th>هوية رب الأسرة</th>
                    <th>اسم الفرد</th>
                    <th>هوية الفرد</th>
                    <th>عمر الفرد</th>
                    <th>جوال الفرد</th>
                    <th>المؤسسة</th>
                    <th>إجراءات</th>
                </tr>
            </thead>
            <tbody>
                @forelse($members as $member)
                    <tr>
                        <td>{{ $member->family?->head_name }}</td>
                        <td>{{ $member->family?->head_national_id }}</td>
                        <td>{{ $member->name }}</td>
                        <td>{{ $member->national_id }}</td>
                        <td>{{ $member->member_age }}</td>
                        <td>{{ $member->mobile }}</td>
                        <td>{{ $member->family?->institution?->name }}</td>
                        <td class="actions">
                            @if($member->family)
                                <a class="btn" href="{{ route('families.edit', $member->family) }}">فتح الأسرة</a>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8">لا توجد نتائج مطابقة.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        {{ $members->links() }}
    </div>

    <div class="mobile-only">
        @forelse($members as $member)
            <div class="card mobile-family-card">
                <div class="mobile-family-head">
                    <strong>{{ $member->name }}</strong>
                    <span>{{ $member->national_id ?: '-' }}</span>
                </div>
                <div class="mobile-family-grid">
                    <div><span>رب الأسرة:</span> {{ $member->family?->head_name ?? '-' }}</div>
                    <div><span>هوية رب الأسرة:</span> {{ $member->family?->head_national_id ?? '-' }}</div>
                    <div><span>العمر:</span> {{ $member->member_age ?? '-' }}</div>
                    <div><span>الجوال:</span> {{ $member->mobile ?? '-' }}</div>
                    <div><span>المؤسسة:</span> {{ $member->family?->institution?->name ?? '-' }}</div>
                </div>
                @if($member->family)
                    <div class="actions mobile-family-actions">
                        <a class="btn" href="{{ route('families.edit', $member->family) }}">فتح الأسرة</a>
                    </div>
                @endif
            </div>
        @empty
            <div class="card">لا توجد نتائج مطابقة.</div>
        @endforelse

        <div class="card">
            {{ $members->links() }}
        </div>
    </div>
@endsection
