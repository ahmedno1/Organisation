@extends('layouts.app')

@section('title', __('ui.families'))
@section('hide_page_head', '1')

@section('content')
    @php
        $governorateOptions = config('family_options.palestine_governorates', []);
        $selectedGovernorate = request('governorate');
        if (! empty($selectedGovernorate) && ! in_array($selectedGovernorate, $governorateOptions, true)) {
            array_unshift($governorateOptions, $selectedGovernorate);
        }
    @endphp

    <div class="section-head">
        <h2>{{ __('ui.families') }}</h2>
        <p class="page-intro">{{ __('ui.families') }}: {{ $families->total() }}</p>
    </div>
    <div class="actions mt-8">
        @if(auth()->user()->hasPermission('manage_families'))
            <a class="btn primary" href="{{ route('families.create') }}">{{ __('ui.add_family_record') }}</a>
            <a class="btn" href="{{ route('orphans.index') }}">{{ __('ui.orphans_list') }}</a>
            <a class="btn" href="{{ route('families.members.search') }}">بحث أفراد الأسر</a>
        @endif
        @if(auth()->user()->hasPermission('export_reports'))
            <a class="btn" href="{{ route('families.export') }}">{{ __('ui.download_all_families_excel') }}</a>
            <a class="btn primary" href="{{ route('families.export', request()->query()) }}">{{ __('ui.download_current_search_results') }}</a>
            <a class="btn" href="{{ route('families.special_export', request()->query()) }}">{{ __('ui.export_special_family_report') }}</a>
        @endif
        @if(auth()->user()->hasPermission('import_families'))
            <form method="POST" action="{{ route('reports.families.import') }}" enctype="multipart/form-data" class="actions" id="families-import-form" data-spa="false">
                @csrf
                <input type="hidden" name="import_type" value="family">
                @if(auth()->user()->isDeveloper())
                    <select name="institution_id" id="families-import-institution" required>
                        <option value="">{{ __('ui.select_institution_for_import') }}</option>
                        @foreach(($filterOptions['institutions'] ?? []) as $inst)
                            <option value="{{ $inst->id }}" @selected((string) old('institution_id') === (string) $inst->id)>{{ $inst->name }}</option>
                        @endforeach
                    </select>
                @endif
                <label>
                    {{ __('ui.files') }}
                    <input type="file" name="files[]" id="families-import-file" required multiple>
                </label>
                <button class="btn primary" type="submit" id="families-import-btn">{{ __('ui.import_families_data') }}</button>
            </form>
        @elseif(auth()->user()->hasPermission('manage_families'))
            <span class="muted">{{ __('ui.import_permission_required') }}</span>
        @endif
    </div>

    <div class="cards-grid mt-16">
        <article class="metric-card">
            <h3>{{ __('ui.families_without_mobile') }}</h3>
            <p>{{ $smartAlerts['missing_mobile'] ?? 0 }}</p>
        </article>
        <article class="metric-card">
            <h3>{{ __('ui.stale_records_90_days') }}</h3>
            <p>{{ $smartAlerts['stale_records'] ?? 0 }}</p>
        </article>
        <article class="metric-card">
            <h3>{{ __('ui.possible_duplicate_ids') }}</h3>
            <p>{{ $smartAlerts['duplicate_national_ids'] ?? 0 }}</p>
        </article>
    </div>

    <div class="card mt-16 toolbar-panel">
        <form method="GET" action="{{ route('families.index') }}" class="form-grid two-cols">
            <label>{{ __('ui.search') }}
                <input type="text" name="q" value="{{ request('q') }}" placeholder="{{ __('ui.search_family_placeholder') }}">
            </label>
            <label>{{ __('ui.marital_distribution') }}
                <input type="text" name="marital_status" value="{{ request('marital_status') }}" placeholder="{{ __('ui.marital_status_example') }}">
            </label>
            <label>{{ __('ui.age_from') }}
                <input type="number" name="min_age" min="0" value="{{ request('min_age') }}">
            </label>
            <label>{{ __('ui.age_to') }}
                <input type="number" name="max_age" min="0" value="{{ request('max_age') }}">
            </label>
            <label>{{ __('ui.governorate') }}
                <select name="governorate">
                    <option value="">{{ __('ui.all_option') }}</option>
                    @foreach($governorateOptions as $governorate)
                        <option value="{{ $governorate }}" @selected($selectedGovernorate === $governorate)>{{ $governorate }}</option>
                    @endforeach
                </select>
            </label>
            <label>{{ __('ui.from_date') }}
                <input type="date" name="from_date" value="{{ request('from_date') }}">
            </label>
            <label>{{ __('ui.to_date') }}
                <input type="date" name="to_date" value="{{ request('to_date') }}">
            </label>
            <label>{{ __('ui.war_injury') }}
                <select name="war_injury">
                    <option value="">{{ __('ui.all_option') }}</option>
                    <option value="1" @selected(request('war_injury') === '1')>{{ __('ui.yes') }}</option>
                    <option value="0" @selected(request('war_injury') === '0')>{{ __('ui.no') }}</option>
                </select>
            </label>
            <label>{{ __('ui.chronic_diseases') }}
                <select name="has_chronic">
                    <option value="">{{ __('ui.all_option') }}</option>
                    <option value="1" @selected(request('has_chronic') === '1')>{{ __('ui.exists') }}</option>
                    <option value="0" @selected(request('has_chronic') === '0')>{{ __('ui.not_exists') }}</option>
                </select>
            </label>
            <label>{{ __('ui.disability') }}
                <select name="has_disability">
                    <option value="">{{ __('ui.all_option') }}</option>
                    <option value="1" @selected(request('has_disability') === '1')>{{ __('ui.exists') }}</option>
                    <option value="0" @selected(request('has_disability') === '0')>{{ __('ui.not_exists') }}</option>
                </select>
            </label>
            <label>{{ __('ui.benefits') }}
                <select name="benefit_id">
                    <option value="">{{ __('ui.all_option') }}</option>
                    <option value="none" @selected(request('benefit_id') === 'none')>{{ __('ui.never_benefited') }}</option>
                    @foreach(($filterOptions['benefits'] ?? []) as $benefit)
                        <option value="{{ $benefit->id }}" @selected((string) request('benefit_id') === (string) $benefit->id)>{{ $benefit->name }}</option>
                    @endforeach
                </select>
            </label>
            <label>{{ __('ui.stale_since_days') }}
                <input type="number" name="stale_days" min="1" value="{{ request('stale_days') }}">
            </label>
            <label>{{ __('ui.sort') }}
                <select name="sort">
                    <option value="latest" @selected(request('sort', 'latest') === 'latest')>{{ __('ui.latest') }}</option>
                    <option value="priority" @selected(request('sort') === 'priority')>{{ __('ui.highest_priority') }}</option>
                </select>
            </label>
            @if(auth()->user()->isDeveloper())
                <label>{{ __('ui.institution') }}
                    <select name="institution_id">
                        <option value="">{{ __('ui.all_option') }}</option>
                        @foreach(($filterOptions['institutions'] ?? []) as $inst)
                            <option value="{{ $inst->id }}" @selected((string) request('institution_id') === (string) $inst->id)>{{ $inst->name }}</option>
                        @endforeach
                    </select>
                </label>
            @endif
            <div class="actions">
                <button class="btn primary" type="submit">{{ __('ui.apply_filters') }}</button>
                <a class="btn" href="{{ route('families.index') }}">{{ __('ui.reset') }}</a>
            </div>
        </form>
    </div>

    <div class="card desktop-only table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th>{{ __('ui.family_head') }}</th>
                    <th>{{ __('ui.identity') }}</th>
                    <th>{{ __('ui.institution') }}</th>
                    <th>{{ __('ui.role_admin') }}</th>
                    <th>{{ __('ui.role_data_entry') }}</th>
                    <th>{{ __('ui.priority_score') }}</th>
                    <th>{{ __('ui.benefits') }}</th>
                    <th>{{ __('ui.actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse($families as $family)
                    <tr>
                        <td>{{ $family->head_name }}</td>
                        <td>{{ $family->head_national_id }}</td>
                        <td>{{ $family->institution?->name }}</td>
                        <td>{{ $family->administrator?->name }}</td>
                        <td>{{ $family->dataEntry?->name }}</td>
                        <td>{{ $family->priority_score }}</td>
                        <td>{{ \Illuminate\Support\Str::limit($family->benefits_from_institution, 40) }}</td>
                        <td class="actions">
                            @if(auth()->user()->hasPermission('edit_families'))
                                <a class="btn" href="{{ route('families.edit', $family) }}">{{ __('ui.edit') }}</a>
                            @endif
                            @if(auth()->user()->hasPermission('delete_families'))
                                <form method="POST" action="{{ route('families.destroy', $family) }}">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn danger" type="submit" onclick="return confirm('{{ __('ui.delete_record_confirm') }}')">{{ __('ui.delete') }}</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8">{{ __('ui.no_families_yet') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        {{ $families->links() }}
    </div>

    <div class="mobile-only">
        @forelse($families as $family)
            <div class="card mobile-family-card">
                <div class="mobile-family-head">
                    <strong>{{ $family->head_name }}</strong>
                    <span>{{ $family->head_national_id }}</span>
                </div>
                <div class="mobile-family-grid">
                    <div><span>{{ __('ui.institution') }}:</span> {{ $family->institution?->name ?? '-' }}</div>
                    <div><span>{{ __('ui.role_admin') }}:</span> {{ $family->administrator?->name ?? '-' }}</div>
                    <div><span>{{ __('ui.role_data_entry') }}:</span> {{ $family->dataEntry?->name ?? '-' }}</div>
                    <div><span>{{ __('ui.priority_score') }}:</span> {{ $family->priority_score }}</div>
                    <div><span>{{ __('ui.benefits') }}:</span> {{ \Illuminate\Support\Str::limit($family->benefits_from_institution, 40) }}</div>
                </div>
                <div class="actions mobile-family-actions">
                    @if(auth()->user()->hasPermission('edit_families'))
                        <a class="btn" href="{{ route('families.edit', $family) }}">{{ __('ui.edit') }}</a>
                    @endif
                    @if(auth()->user()->hasPermission('delete_families'))
                        <form method="POST" action="{{ route('families.destroy', $family) }}">
                            @csrf
                            @method('DELETE')
                            <button class="btn danger" type="submit" onclick="return confirm('{{ __('ui.delete_record_confirm') }}')">{{ __('ui.delete') }}</button>
                        </form>
                    @endif
                </div>
            </div>
        @empty
            <div class="card">
                {{ __('ui.no_families_yet') }}
            </div>
        @endforelse

        <div class="card">
            {{ $families->links() }}
        </div>
    </div>
@endsection
