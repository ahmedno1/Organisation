@extends('layouts.app')

@section('title', __('ui.user_permissions'))

@section('content')
    <div class="card">
        <h2>{{ __('ui.edit_permissions_for') }}: {{ $user->name }} ({{ $user->email }})</h2>
        <form method="POST" action="{{ route('developers.permissions.update', $user) }}" class="form-grid">
            @csrf
            @method('PUT')

            <div class="form-grid two-cols">
                @foreach($permissionKeys as $permissionKey)
                    <label class="checkbox-row">
                        <input type="checkbox" name="permissions[{{ $permissionKey }}]" value="1" @checked(old("permissions.$permissionKey", $effectivePermissions[$permissionKey] ?? false))>
                        <span>{{ $permissionKey }}</span>
                    </label>
                @endforeach
            </div>

            <button class="btn primary" type="submit">{{ __('ui.save_permissions') }}</button>
        </form>
    </div>
@endsection
