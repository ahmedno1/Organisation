@extends('layouts.app')

@section('title', __('ui.reports'))

@section('content')
    <div class="section-head">
        <h2>{{ __('ui.reports') }}</h2>
        <p class="page-intro">{{ __('ui.export_statistics') }} + {{ __('ui.orphans_list') }}</p>
        <div class="actions">
            @if(auth()->user()->hasPermission('export_reports'))
                <a class="btn primary" href="{{ route('reports.families.export') }}">{{ __('ui.export_families_excel') }}</a>
                <a class="btn" href="{{ route('reports.orphans.export') }}">{{ __('ui.export_orphans_excel') }}</a>
                <a class="btn" href="{{ route('reports.statistics.export') }}">{{ __('ui.export_statistics') }}</a>
                <a class="btn" href="{{ route('reports.print', request()->query()) }}" target="_blank">{{ __('ui.print_pdf') }}</a>
            @endif
        </div>
    </div>

    @if(auth()->user()->hasPermission('import_families'))
        <div class="card mt-16">
            <h3>{{ __('ui.import_families_data') }}</h3>
            <p class="muted">{{ __('ui.import_required_columns_note') }} <code>head_name,head_national_id</code></p>
            <form method="POST" action="{{ route('reports.families.import') }}" enctype="multipart/form-data" class="actions">
                @csrf
                @if(auth()->user()->isDeveloper())
                    <select name="institution_id" required>
                        <option value="">{{ __('ui.select_institution_for_import') }}</option>
                        @foreach(($institutions ?? collect()) as $institution)
                            <option value="{{ $institution->id }}" @selected((string) old('institution_id') === (string) $institution->id)>
                                {{ $institution->name }}
                            </option>
                        @endforeach
                    </select>
                @endif
                <input type="file" name="file" required>
                <button class="btn primary" type="submit">{{ __('ui.import') }}</button>
            </form>
        </div>
    @else
        <div class="card mt-16">
            <p class="muted">{{ __('ui.import_permission_required') }}</p>
        </div>
    @endif

    <div class="card mt-16 table-wrap">
        <h3>{{ __('ui.widows_search_download') }}</h3>
        <form method="GET" action="{{ route('reports.index') }}" class="actions">
            <select name="widow_status">
                <option value="">{{ __('ui.all_widow_statuses') }}</option>
                <option value="ارمل" @selected(request('widow_status') === 'ارمل')>{{ __('ui.widower') }}</option>
                <option value="ارملة" @selected(request('widow_status') === 'ارملة')>{{ __('ui.widow') }}</option>
            </select>
            <button class="btn" type="submit">{{ __('ui.search') }}</button>
            <a class="btn" href="{{ route('reports.index') }}">{{ __('ui.reset') }}</a>
            @if(auth()->user()->hasPermission('export_reports'))
                <a class="btn primary" href="{{ route('reports.widows.export', ['widow_status' => request('widow_status')]) }}">{{ __('ui.download_results_csv') }}</a>
            @endif
        </form>

        <table class="table">
            <thead>
                <tr>
                    <th>{{ __('ui.family_head_name') }}</th>
                    <th>{{ __('ui.identity') }}</th>
                    <th>{{ __('ui.marital_distribution') }}</th>
                    <th>{{ __('ui.family_members_total') }}</th>
                    <th>{{ __('ui.registered_children_count') }}</th>
                    <th>{{ __('ui.institution') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse($widowFamilies as $family)
                    <tr>
                        <td>{{ $family->head_name }}</td>
                        <td>{{ $family->head_national_id }}</td>
                        <td>{{ $family->marital_status }}</td>
                        <td>{{ $family->family_members_count }}</td>
                        <td>{{ $family->members_count }}</td>
                        <td>{{ $family->institution?->name }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6">{{ __('ui.no_matching_results') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        {{ $widowFamilies->links() }}
    </div>

    <div class="cards-grid">
        <article class="metric-card">
            <h3>{{ __('ui.total_families') }}</h3>
            <p>{{ $statistics['total_families'] }}</p>
        </article>
        <article class="metric-card">
            <h3>{{ __('ui.orphans_count') }}</h3>
            <p>{{ $statistics['orphans_count'] ?? 0 }}</p>
        </article>
        <article class="metric-card">
            <h3>{{ __('ui.total_registered_family_members') }}</h3>
            <p>{{ $statistics['total_members'] }}</p>
        </article>
        <article class="metric-card">
            <h3>{{ __('ui.war_injuries') }}</h3>
            <p>{{ $statistics['war_injury_count'] }}</p>
        </article>
        <article class="metric-card">
            <h3>{{ __('ui.chronic_diseases') }}</h3>
            <p>{{ $statistics['chronic_disease_count'] }}</p>
        </article>
        <article class="metric-card">
            <h3>{{ __('ui.data_entry_users') }}</h3>
            <p>{{ $statistics['data_entry_users'] }}</p>
        </article>
    </div>

    <div class="card mt-16 table-wrap">
        <h3>{{ __('ui.orphans_list') }}</h3>
        <table class="table">
            <thead>
                <tr>
                    <th>{{ __('ui.family_head_name') }}</th>
                    <th>{{ __('ui.identity') }}</th>
                    <th>{{ __('ui.family_members_total') }}</th>
                    <th>{{ __('ui.institution') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse($orphanFamilies as $orphanFamily)
                    <tr>
                        <td>{{ $orphanFamily->head_name }}</td>
                        <td>{{ $orphanFamily->head_national_id }}</td>
                        <td>{{ $orphanFamily->family_members_count }}</td>
                        <td>{{ $orphanFamily->institution?->name }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4">{{ __('ui.no_matching_results') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        {{ $orphanFamilies->links() }}
    </div>

    <div class="card mt-16 table-wrap">
        <h3>{{ __('ui.marital_distribution') }}</h3>
        <div class="chart-box">
            <canvas id="reportMaritalChart"></canvas>
        </div>
        <table class="table">
            <thead>
                <tr>
                    <th>{{ __('ui.status') }}</th>
                    <th>{{ __('ui.chart_count') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse($statistics['marital_status'] as $status => $count)
                    <tr>
                        <td>{{ $status ?: __('ui.not_specified') }}</td>
                        <td>{{ $count }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="2">{{ __('ui.no_data_yet') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        (() => {
            const marital = @json($statistics['marital_status'] ?? []);
            const el = document.getElementById('reportMaritalChart');
            if (!window.Chart || !el) return;
            new Chart(el, {
                type: 'bar',
                data: {
                    labels: Object.keys(marital),
                    datasets: [{
                        label: @json(__('ui.chart_count')),
                        data: Object.values(marital),
                        backgroundColor: '#0f766e'
                    }]
                },
                options: { responsive: true, maintainAspectRatio: false }
            });
        })();
    </script>
@endpush
