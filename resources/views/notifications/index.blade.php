@extends('layouts.app')

@section('title', __('ui.notifications'))

@section('content')
    <div class="section-head">
        <h2>{{ __('ui.notifications') }}</h2>
        <form method="POST" action="{{ route('notifications.read_all') }}">
            @csrf
            <button class="btn" type="submit">{{ __('ui.mark_all_read') }}</button>
        </form>
    </div>

    <div class="card">
        <table class="table">
            <thead>
                <tr>
                    <th>{{ __('ui.title') }}</th>
                    <th>{{ __('ui.message') }}</th>
                    <th>{{ __('ui.time') }}</th>
                    <th>{{ __('ui.status') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse($notifications as $notification)
                    <tr>
                        <td>{{ $notification->data['title'] ?? __('ui.notification') }}</td>
                        <td>{{ $notification->data['message'] ?? __('ui.dash') }}</td>
                        <td>{{ $notification->created_at?->format('Y-m-d H:i') }}</td>
                        <td>{{ $notification->read_at ? __('ui.read') : __('ui.unread') }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4">{{ __('ui.no_notifications') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        {{ $notifications->links() }}
    </div>
@endsection
