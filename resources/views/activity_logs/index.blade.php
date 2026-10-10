@extends('layouts.app')

@section('title', __('ui.activity_logs'))

@section('content')
    <div class="activity-layout">
        <div class="card">
            <form method="GET" class="form-grid two-cols">
                <label>
                    {{ __('ui.user') }}
                    <select name="user_id">
                        <option value="">-- {{ __('ui.all') }} --</option>
                        @foreach($users as $userItem)
                            <option value="{{ $userItem->id }}" @selected((string) request('user_id') === (string) $userItem->id)>
                                {{ $userItem->name }} - {{ $userItem->email }}
                            </option>
                        @endforeach
                    </select>
                </label>

                <label>
                    {{ __('ui.action') }}
                    <select name="action">
                        <option value="">-- {{ __('ui.all') }} --</option>
                        @foreach($actions as $actionItem)
                            <option value="{{ $actionItem->action }}" @selected(request('action') === $actionItem->action)>
                                {{ $actionItem->action_label }} ({{ $actionItem->total }})
                            </option>
                        @endforeach
                    </select>
                </label>

                <label>
                    {{ __('ui.from_date') }}
                    <input type="date" name="date_from" value="{{ request('date_from') }}">
                </label>

                <label>
                    {{ __('ui.to_date') }}
                    <input type="date" name="date_to" value="{{ request('date_to') }}">
                </label>

                <div class="actions">
                    <button class="btn primary" type="submit">{{ __('ui.filter') }}</button>
                    <a class="btn" href="{{ route('activity_logs.index') }}">{{ __('ui.reset') }}</a>
                    <a class="btn" href="#sessions-section">{{ __('ui.sessions_log') }}</a>
                </div>
            </form>
        </div>

        <div class="card">
            <h3 class="activity-heading">{{ __('ui.actions_list') }}</h3>
            <div class="activity-actions-list">
                @forelse($actions as $actionItem)
                    <a class="activity-action-chip" href="{{ route('activity_logs.index', array_merge(request()->query(), ['action' => $actionItem->action])) }}">
                        <span>{{ $actionItem->action_label }}</span>
                        <strong>{{ $actionItem->total }}</strong>
                    </a>
                @empty
                    <span class="muted">{{ __('ui.no_actions_logged') }}</span>
                @endforelse
            </div>
        </div>
    </div>

    <div class="card mt-16">
        <h3 class="activity-heading">{{ __('ui.activity_logs') }}</h3>
        <table class="table">
            <thead>
                <tr>
                    <th>{{ __('ui.user') }}</th>
                    <th>{{ __('ui.action') }}</th>
                    <th>{{ __('ui.subject') }}</th>
                    <th>IP</th>
                    <th>{{ __('ui.time') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse($logs as $log)
                    <tr>
                        <td>{{ $log->user?->email ?? 'guest' }}</td>
                        <td>{{ $log->action_label }}</td>
                        <td>{{ class_basename($log->subject_type ?? '-') }} #{{ $log->subject_id ?? '-' }}</td>
                        <td>{{ $log->ip }}</td>
                        <td>{{ $log->created_at?->format('Y-m-d H:i') }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5">{{ __('ui.no_activity_logs') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        {{ $logs->links() }}
    </div>

    <div class="card mt-16" id="sessions-section">
        <h3 class="activity-heading">{{ __('ui.sessions_log') }}</h3>
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
