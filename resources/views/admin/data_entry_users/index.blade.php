@extends('layouts.app')

@section('title', __('ui.data_entry_users'))

@section('content')
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

    <div class="section-head">
        <h2>{{ __('ui.data_entry_users_for_you') }}</h2>
        <a class="btn primary" href="{{ route('admin.data-entry-users.create') }}">{{ __('ui.add_data_entry_user') }}</a>
    </div>

    <div class="card">
        <table class="table">
            <thead>
                <tr>
                    <th>{{ __('ui.name') }}</th>
                    <th>{{ __('ui.email') }}</th>
                    <th>{{ __('ui.created_at') }}</th>
                    <th>{{ __('ui.actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse($dataEntryUsers as $dataEntryUser)
                    <tr>
                        <td>{{ $dataEntryUser->name }}</td>
                        <td>{{ $dataEntryUser->email }}</td>
                        <td>{{ $dataEntryUser->created_at->format('Y-m-d') }}</td>
                        <td class="actions">
                            <a class="btn" href="{{ route('admin.data-entry-users.edit', $dataEntryUser) }}">{{ __('ui.edit') }}</a>
                            <form method="POST" action="{{ route('admin.data-entry-users.destroy', $dataEntryUser) }}">
                                @csrf
                                @method('DELETE')
                                <button class="btn danger" type="submit" onclick="return confirm(@js(__('ui.delete_data_entry_confirm')))">{{ __('ui.delete') }}</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4">{{ __('ui.no_data_entry_users') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        {{ $dataEntryUsers->links() }}
    </div>
@endsection

