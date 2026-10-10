@extends('layouts.app')

@section('title', __('ui.edit_data_entry_user'))

@section('content')
    <div class="card institution-banner">
        <div class="institution-cell">
            @if(optional(auth()->user()->institution)->logo_url)
                <img
                    src="{{ auth()->user()->institution->logo_url }}"
                    alt="Institution Logo"
                    class="institution-logo"
                >
            @endif
            <div>
                <h3>{{ __('ui.institution') }}</h3>
                <p>{{ auth()->user()->institution?->name ?? __('ui.not_specified') }}</p>
            </div>
        </div>
    </div>

    <div class="card">
        <h2>{{ __('ui.edit_data_entry_user') }}</h2>
        <form method="POST" action="{{ route('admin.data-entry-users.update', $dataEntryUser) }}" class="form-grid">
            @csrf
            @method('PUT')
            @include('admin.data_entry_users._form', ['submitLabel' => __('ui.update')])
        </form>
    </div>
@endsection

