@extends('layouts.app')

@section('title', __('ui.dashboard'))

@section('content')
    <div class="dashboard">
        @if(auth()->user()->isAdmin() || auth()->user()->isDataEntry())
            <div class="card institution-banner">
                <div class="institution-cell">
                    @if(optional(auth()->user()->institution)->logo_url)
                        <img
                            src="{{ auth()->user()->institution->logo_url }}"
                            alt="Institution Logo"
                            class="institution-logo institution-logo-lg"
                        >
                    @endif
                    <div>
                        <h3>{{ __('ui.institution') }}</h3>
                        <p>{{ auth()->user()->institution?->name ?? __('ui.not_specified') }}</p>
                    </div>
                </div>
            </div>
        @endif

        <div class="cards-grid">
            <article class="metric-card">
                <h3>{{ __('ui.families_count') }}</h3>
                <p>{{ $stats['families'] }}</p>
            </article>

            <article class="metric-card">
                <h3>{{ __('ui.orphans_count') }}</h3>
                <p>{{ $stats['orphans'] ?? 0 }}</p>
            </article>

            <article class="metric-card">
                <h3>{{ __('ui.members_count') }}</h3>
                <p>{{ $stats['members'] }}</p>
            </article>

            @if($institutionStorage)
                <article class="metric-card">
                    <h3>{{ __('ui.institution_storage_quota_card') }}</h3>
                    <p>
                        @if($institutionStorage['quota_mb'] !== null)
                            {{ $institutionStorage['quota_mb'] }} MB
                        @else
                            {{ __('ui.institution_storage_unlimited') }}
                        @endif
                    </p>
                </article>

                <article class="metric-card">
                    <h3>{{ __('ui.institution_storage_used_card') }}</h3>
                    <p>{{ $institutionStorage['used_mb'] }} MB</p>
                    @if($institutionStorage['usage_percent'] !== null)
                        <small class="muted">{{ $institutionStorage['usage_percent'] }}%</small>
                    @endif
                </article>
            @endif

            @if(auth()->user()->isAdmin())
                <article class="metric-card">
                    <h3>{{ __('ui.data_entry_count') }}</h3>
                    <p>{{ $stats['data_entry_users'] }}</p>
                </article>
            @endif

            @if(auth()->user()->isDeveloper())
                <article class="metric-card">
                    <h3>{{ __('ui.admins_count') }}</h3>
                    <p>{{ $stats['administrators'] }}</p>
                </article>
            @endif
        </div>

        <div class="cards-grid">
            <article class="metric-card">
                <h3>{{ __('ui.stale_records_alert') }}</h3>
                <p>{{ $alerts['stale_records'] ?? 0 }}</p>
            </article>
            <article class="metric-card">
                <h3>{{ __('ui.missing_mobile_alert') }}</h3>
                <p>{{ $alerts['missing_mobile'] ?? 0 }}</p>
            </article>
            <article class="metric-card">
                <h3>{{ __('ui.high_priority_cases') }}</h3>
                <p>{{ $alerts['high_priority'] ?? 0 }}</p>
            </article>
        </div>

        <div class="dashboard-charts">
            <div class="card">
                <h3>{{ __('ui.monthly_registration_trend') }}</h3>
                <p class="muted">{{ now()->format('Y-m') }} (يومي)</p>
                <div class="chart-box">
                    <canvas id="monthlyFamiliesChart"></canvas>
                </div>
            </div>

            <div class="card">
                <h3>{{ __('ui.monthly_orphans_registration_trend') }}</h3>
                <p class="muted">{{ now()->format('Y-m') }} (يومي)</p>
                <div class="chart-box">
                    <canvas id="monthlyOrphansChart"></canvas>
                </div>
            </div>

            <div class="card">
                <h3>{{ __('ui.marital_distribution') }}</h3>
                <div class="chart-box">
                    <canvas id="maritalStatusChart"></canvas>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        (() => {
            const monthly = @json($familiesMonthly ?? []);
            const monthlyOrphans = @json($orphansMonthly ?? []);
            const marital = @json($maritalStats ?? []);
            let chartsRendered = false;

            const formatDayLabel = (value) => {
                const date = String(value || '');
                return date.length >= 10 ? date.slice(8, 10) : date;
            };

            const renderCharts = () => {
                if (chartsRendered || !window.Chart) {
                    return;
                }

                chartsRendered = true;

                if (document.getElementById('monthlyFamiliesChart')) {
                    new Chart(document.getElementById('monthlyFamiliesChart'), {
                        type: 'line',
                        data: {
                            labels: Object.keys(monthly).map(formatDayLabel),
                            datasets: [{
                                label: @json(__('ui.chart_families')),
                                data: Object.values(monthly),
                                borderColor: '#0f766e',
                                backgroundColor: 'rgba(15,118,110,0.15)',
                                borderWidth: 2,
                                fill: true,
                                tension: 0.3
                            }]
                        },
                        options: { responsive: true, maintainAspectRatio: false }
                    });
                }

                if (document.getElementById('monthlyOrphansChart')) {
                    new Chart(document.getElementById('monthlyOrphansChart'), {
                        type: 'line',
                        data: {
                            labels: Object.keys(monthlyOrphans).map(formatDayLabel),
                            datasets: [{
                                label: @json(__('ui.orphans_count')),
                                data: Object.values(monthlyOrphans),
                                borderColor: '#1d4ed8',
                                backgroundColor: 'rgba(29,78,216,0.15)',
                                borderWidth: 2,
                                fill: true,
                                tension: 0.3
                            }]
                        },
                        options: { responsive: true, maintainAspectRatio: false }
                    });
                }

                if (document.getElementById('maritalStatusChart')) {
                    new Chart(document.getElementById('maritalStatusChart'), {
                        type: 'bar',
                        data: {
                            labels: Object.keys(marital),
                            datasets: [{
                                label: @json(__('ui.chart_count')),
                                data: Object.values(marital),
                                backgroundColor: ['#0f766e','#1d4ed8','#f59e0b','#ef4444','#8b5cf6','#14b8a6']
                            }]
                        },
                        options: { responsive: true, maintainAspectRatio: false }
                    });
                }
            };

            const ensureChartThenRender = () => {
                if (window.Chart) {
                    renderCharts();
                    return;
                }

                const script = Array.from(document.querySelectorAll('script[src]'))
                    .find((el) => (el.getAttribute('src') || '').includes('cdn.jsdelivr.net/npm/chart.js'));

                if (!script) {
                    return;
                }

                script.addEventListener('load', renderCharts, { once: true });
                script.addEventListener('error', () => { chartsRendered = false; }, { once: true });
            };

            ensureChartThenRender();
        })();
    </script>
@endpush

