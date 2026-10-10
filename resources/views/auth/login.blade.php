@extends('layouts.app')

@section('title', __('ui.login_title'))
@section('hide_page_head', '1')
@section('main_content_class', 'main-content-centered')
@section('page_layout_class', 'centered-page-layout')

@section('content')
    @php
        $appLogoCandidates = [
            'images/esnad-logo.png',
            'images/esnad-logo.jpg',
            'images/esnad-logo.jpeg',
            'images/esnad-logo.webp',
        ];
        $appLogoRelativePath = collect($appLogoCandidates)->first(fn ($path) => is_file(public_path($path)));
        $appLogoExists = ! empty($appLogoRelativePath);
    @endphp

    <div class="card narrow">
        @if($appLogoExists)
            <div class="auth-logo-wrap">
                <img src="{{ asset($appLogoRelativePath) }}" alt="{{ __('ui.platform_logo_alt') }}" class="auth-platform-logo">
            </div>
        @endif

        <h2>{{ __('ui.login_title') }}</h2>
        <p class="muted">{{ __('ui.login_subtitle') }}</p>

        <form method="POST" action="{{ route('login.store') }}" class="form-grid">
            @csrf
            <label>
                {{ __('ui.email') }}
                <input type="email" name="email" value="{{ old('email') }}" required>
            </label>

            <label>
                {{ __('ui.password') }}
                <input type="password" name="password" required>
            </label>

            <label class="checkbox-row">
                <input type="checkbox" name="remember" value="1">
                {{ __('ui.remember_me') }}
            </label>

            <button class="btn primary" type="submit">{{ __('ui.login_button') }}</button>
        </form>
    </div>
@endsection
