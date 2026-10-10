@extends('layouts.app')

@section('title', __('ui.administrators'))

@section('content')
    <div class="section-head">
        <h2>{{ __('ui.administrators') }}</h2>
        <a class="btn primary" href="{{ route('developers.administrators.create') }}">{{ __('ui.add_administrator') }}</a>
    </div>

    <div class="card">
        <table class="table">
            <thead>
                <tr>
                    <th>{{ __('ui.name') }}</th>
                    <th>{{ __('ui.email') }}</th>
                    <th>{{ __('ui.institution') }}</th>
                    <th>{{ __('ui.data_entry_count') }}</th>
                    <th>{{ __('ui.actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse($administrators as $administrator)
                    <tr>
                        <td>{{ $administrator->name }}</td>
                        <td>{{ $administrator->email }}</td>
                        <td>
                            <div class="institution-cell">
                                @if(optional($administrator->institution)->logo_url)
                                    <img
                                        src="{{ $administrator->institution->logo_url }}"
                                        alt="{{ __('ui.institution_logo') }}"
                                        class="institution-logo"
                                    >
                                @endif
                                <span>{{ $administrator->institution?->name }}</span>
                            </div>
                        </td>
                        <td>{{ $administrator->data_entry_users_count }}</td>
                        <td class="actions">
                            <a class="btn" href="{{ route('developers.administrators.edit', $administrator) }}">{{ __('ui.edit') }}</a>
                            <form method="POST" action="{{ route('developers.administrators.destroy', $administrator) }}">
                                @csrf
                                @method('DELETE')
                                <button class="btn danger" type="submit" onclick="return confirm('{{ __('ui.delete_admin_confirm') }}')">{{ __('ui.delete') }}</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5">{{ __('ui.no_administrators') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        {{ $administrators->links() }}
    </div>
@endsection

