@extends('layouts.app')

@section('title', __('ui.orphans_list'))
@section('hide_page_head', '1')

@section('content')
    <div class="section-head">
        <h2>{{ __('ui.orphans_list') }}</h2>
        <p class="page-intro">{{ __('ui.orphans_list') }}: {{ $orphans->total() }}</p>
        <div class="actions">
            @if(auth()->user()->hasPermission('manage_families'))
                <a class="btn primary" href="{{ route('orphans.create') }}">{{ __('ui.add_orphan') }}</a>
            @endif
            @if(auth()->user()->hasPermission('export_reports'))
                <a class="btn" href="{{ route('reports.orphans.export') }}">{{ __('ui.export_orphans_excel') }}</a>
            @endif
            @if(auth()->user()->hasPermission('import_families'))
                <form method="POST" action="{{ route('reports.families.import') }}" enctype="multipart/form-data" class="actions" id="orphans-page-import-form" data-spa="false">
                    @csrf
                    <input type="hidden" name="import_type" value="orphan">
                    @if(auth()->user()->isDeveloper())
                        <select name="institution_id" id="orphans-page-import-institution" required>
                            <option value="">{{ __('ui.select_institution_for_import') }}</option>
                            @foreach(($institutions ?? collect()) as $institution)
                                <option value="{{ $institution->id }}">{{ $institution->name }}</option>
                            @endforeach
                        </select>
                    @endif
                    <label>
                        {{ __('ui.files') }}
                        <input type="file" name="files[]" id="orphans-page-import-file" required multiple>
                    </label>
                    <button class="btn primary" type="submit" id="orphans-page-import-btn">{{ __('ui.import_orphans_data') }}</button>
                </form>
            @endif
        </div>
    </div>

    <div class="kpi-strip mt-8">
        <article class="kpi-pill">
            <h4>{{ __('ui.orphans_count') }}</h4>
            <p>{{ $orphans->total() }}</p>
        </article>
        <article class="kpi-pill">
            <h4>{{ __('ui.status') }}</h4>
            <p>{{ __('ui.active') }}</p>
        </article>
    </div>

    <div class="card mt-16 toolbar-panel">
        <form method="GET" action="{{ route('orphans.index') }}" class="form-grid two-cols">
            <label>{{ __('ui.search') }}
                <input type="text" name="q" value="{{ request('q') }}" placeholder="{{ __('ui.search_family_placeholder') }}">
            </label>

            @if(auth()->user()->isDeveloper())
                <label>{{ __('ui.institution') }}
                    <select name="institution_id">
                        <option value="">{{ __('ui.all_option') }}</option>
                        @foreach(($institutions ?? collect()) as $institution)
                            <option value="{{ $institution->id }}" @selected((string) request('institution_id') === (string) $institution->id)>
                                {{ $institution->name }}
                            </option>
                        @endforeach
                    </select>
                </label>
            @endif

            <div class="actions">
                <button class="btn primary" type="submit">{{ __('ui.apply_filters') }}</button>
                <a class="btn" href="{{ route('orphans.index') }}">{{ __('ui.reset') }}</a>
                <a class="btn" href="{{ route('families.index') }}">{{ __('ui.families') }}</a>
            </div>
        </form>
    </div>

    <div class="card mt-16 desktop-only table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th>{{ __('ui.family_head') }}</th>
                    <th>{{ __('ui.identity') }}</th>
                    <th>{{ __('ui.institution') }}</th>
                    <th>{{ __('ui.role_admin') }}</th>
                    <th>{{ __('ui.role_data_entry') }}</th>
                    <th>{{ __('ui.family_members_total') }}</th>
                    <th>{{ __('ui.actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse($orphans as $orphan)
                    <tr>
                        <td>{{ $orphan->head_name }}</td>
                        <td>{{ $orphan->head_national_id }}</td>
                        <td>{{ $orphan->institution?->name }}</td>
                        <td>{{ $orphan->administrator?->name }}</td>
                        <td>{{ $orphan->dataEntry?->name }}</td>
                        <td>{{ $orphan->family_members_count }}</td>
                        <td class="actions">
                            @if(auth()->user()->hasPermission('edit_families'))
                                <a class="btn" href="{{ route('orphans.edit', $orphan) }}">{{ __('ui.edit') }}</a>
                            @endif
                            @if(auth()->user()->hasPermission('delete_families'))
                                <form method="POST" action="{{ route('orphans.destroy', $orphan) }}">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn danger" type="submit" onclick="return confirm('{{ __('ui.delete_record_confirm') }}')">{{ __('ui.delete') }}</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7">{{ __('ui.no_matching_results') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        {{ $orphans->links() }}
    </div>

    <div class="mobile-only">
        @forelse($orphans as $orphan)
            <div class="card mobile-family-card">
                <div class="mobile-family-head">
                    <strong>{{ $orphan->head_name }}</strong>
                    <span>{{ $orphan->head_national_id }}</span>
                </div>
                <div class="mobile-family-grid">
                    <div><span>{{ __('ui.institution') }}:</span> {{ $orphan->institution?->name ?? '-' }}</div>
                    <div><span>{{ __('ui.role_admin') }}:</span> {{ $orphan->administrator?->name ?? '-' }}</div>
                    <div><span>{{ __('ui.role_data_entry') }}:</span> {{ $orphan->dataEntry?->name ?? '-' }}</div>
                    <div><span>{{ __('ui.family_members_total') }}:</span> {{ $orphan->family_members_count }}</div>
                </div>
                <div class="actions mobile-family-actions">
                    @if(auth()->user()->hasPermission('edit_families'))
                        <a class="btn" href="{{ route('orphans.edit', $orphan) }}">{{ __('ui.edit') }}</a>
                    @endif
                    @if(auth()->user()->hasPermission('delete_families'))
                        <form method="POST" action="{{ route('orphans.destroy', $orphan) }}">
                            @csrf
                            @method('DELETE')
                            <button class="btn danger" type="submit" onclick="return confirm('{{ __('ui.delete_record_confirm') }}')">{{ __('ui.delete') }}</button>
                        </form>
                    @endif
                </div>
            </div>
        @empty
            <div class="card">
                {{ __('ui.no_matching_results') }}
            </div>
        @endforelse

        <div class="card">
            {{ $orphans->links() }}
        </div>
    </div>
@endsection

