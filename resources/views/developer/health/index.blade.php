@extends('layouts.app')

@section('title', __('ui.health_check'))

@section('content')
    @php
        $labels = [
            'database' => __('ui.health_database'),
            'cache' => __('ui.health_cache'),
            'queue' => __('ui.health_queue'),
            'api_key' => __('ui.health_api'),
            'backups' => __('ui.health_backups'),
            'notifications' => __('ui.notifications'),
            'users' => __('ui.health_users'),
        ];
    @endphp

    <div class="section-head">
        <h2>{{ __('ui.system_status') }}</h2>
        <form method="POST" action="{{ route('developers.health.run_backup') }}">
            @csrf
            <button class="btn primary" type="submit">{{ __('ui.run_backup_now') }}</button>
        </form>
    </div>

    <div class="cards-grid">
        @foreach($checks as $name => $check)
            <article class="metric-card">
                <h3>{{ $labels[$name] ?? ucfirst(str_replace('_', ' ', $name)) }}</h3>
                <p>
                    @if($check['status'] === 'ok')
                        {{ __('ui.ok') }}
                    @elseif($check['status'] === 'warn')
                        {{ __('ui.warning') }}
                    @else
                        {{ __('ui.failed') }}
                    @endif
                </p>
                <small class="muted">{{ $check['details'] }}</small>
            </article>
        @endforeach
    </div>
@endsection
