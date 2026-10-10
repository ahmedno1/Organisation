@extends('layouts.app')

@section('title', __('ui.sessions_log'))

@section('content')
    <div class="card">
        <table class="table">
            <thead>
                <tr>
                    <th>{{ __('ui.user') }}</th>
                    <th>IP</th>
                    <th>{{ __('ui.browser') }}</th>
                    <th>{{ __('ui.last_activity') }}</th>
                    <th>{{ __('ui.status') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse($sessions as $session)
                    <tr>
                        <td>{{ $session->user?->email }}</td>
                        <td>{{ $session->ip }}</td>
                        <td>{{ \Illuminate\Support\Str::limit($session->user_agent, 60) }}</td>
                        <td>{{ optional($session->last_activity_at)->format('Y-m-d H:i') }}</td>
                        <td>{{ $session->is_current ? __('ui.active') : __('ui.old') }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5">{{ __('ui.no_sessions') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        {{ $sessions->links() }}
    </div>
@endsection
