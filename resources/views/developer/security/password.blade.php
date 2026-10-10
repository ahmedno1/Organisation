@extends('layouts.app')

@section('title', __('ui.account_security'))
@section('hide_page_head', '1')
@section('main_content_class', 'main-content-centered')
@section('page_layout_class', 'centered-page-layout')

@section('content')
    @php
        $isAdminSecurity = auth()->user()?->isAdmin();
        $isDeveloperSecurity = auth()->user()?->isDeveloper();
        $securityUpdateRoute = auth()->user()?->isAdmin()
            ? route('admin.security.password.update')
            : route('developers.security.password.update');
    @endphp

    <div class="card narrow">
        <h3>{{ __('ui.account_security') }}</h3>
        <form method="POST" action="{{ $securityUpdateRoute }}" class="form-grid">
            @csrf
            @method('PUT')

            @if($isDeveloperSecurity)
                <label>
                    {{ __('ui.email') }}
                    <input type="email" name="email" value="{{ old('email', auth()->user()?->email) }}" required>
                </label>
            @endif

            <label>
                {{ __('ui.current_password') }}
                <input type="password" name="current_password" required>
            </label>

            <label>
                {{ __('ui.new_password') }}
                <input type="password" name="password" @if($isAdminSecurity) required @endif>
            </label>

            <label>
                {{ __('ui.confirm_new_password') }}
                <input type="password" name="password_confirmation">
            </label>

            <button class="btn primary" type="submit">{{ __('ui.update') }}</button>
        </form>
    </div>
@endsection
