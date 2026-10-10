<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#0f4c7a" media="(prefers-color-scheme: light)">
    <meta name="theme-color" content="#0b1524" media="(prefers-color-scheme: dark)">
    <meta name="theme-color" content="#0f4c7a" id="dynamic-theme-color">
    <meta name="color-scheme" content="light dark">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    @php
        $logoIconVersion = is_file(public_path('images/esnad-logo.png'))
            ? (string) filemtime(public_path('images/esnad-logo.png'))
            : (string) now()->timestamp;
    @endphp
    <link rel="manifest" href="{{ asset('manifest.webmanifest') }}?v={{ $logoIconVersion }}">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('images/esnad-logo.png') }}?v={{ $logoIconVersion }}">
    <link rel="icon" type="image/png" sizes="192x192" href="{{ asset('images/esnad-logo.png') }}?v={{ $logoIconVersion }}">
    <link rel="shortcut icon" type="image/png" href="{{ asset('images/esnad-logo.png') }}?v={{ $logoIconVersion }}">
    <link rel="apple-touch-icon" href="{{ asset('images/esnad-logo.png') }}?v={{ $logoIconVersion }}">
    <script>
        (function () {
            const themeKey = 'esnad_theme';
            let theme = null;

            try {
                theme = localStorage.getItem(themeKey);
            } catch (error) {
                theme = null;
            }

            if (theme !== 'dark' && theme !== 'light') {
                theme = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
            }

            document.documentElement.setAttribute('data-theme', theme);
        })();
    </script>
    <title>@yield('title', __('ui.site_name'))</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="app-shell"
    data-i18n-online="{{ __('ui.online') }}"
    data-i18n-offline="{{ __('ui.offline') }}"
    data-i18n-queue-label="{{ __('ui.queue_label', ['count' => '__COUNT__']) }}"
    data-i18n-offline-upload-error="{{ __('ui.offline_upload_error') }}"
    data-i18n-offline-unknown-error="{{ __('ui.offline_unknown_error') }}"
    data-i18n-offline-file-missing="{{ __('ui.offline_file_missing') }}"
    data-i18n-offline-file-lookup-error="{{ __('ui.offline_file_lookup_error') }}"
    data-i18n-offline-saved="{{ __('ui.offline_saved') }}"
    data-i18n-offline-duplicate="{{ __('ui.offline_duplicate') }}"
    data-i18n-offline-save-failed="{{ __('ui.offline_save_failed') }}"
    data-i18n-theme-dark="{{ __('ui.theme_dark') }}"
    data-i18n-theme-light="{{ __('ui.theme_light') }}"
>
    <div id="global-connection-status" class="global-connection-status is-hidden" aria-live="polite"></div>

    @php
        $appLogoCandidates = [
            'images/esnad-logo.png',
            'images/esnad-logo.jpg',
            'images/esnad-logo.jpeg',
            'images/esnad-logo.webp',
        ];
        $appLogoRelativePath = collect($appLogoCandidates)->first(fn ($path) => is_file(public_path($path)));
        $appLogoExists = ! empty($appLogoRelativePath);
        $currentLocale = app()->getLocale();
    @endphp

    <div class="bg-orb orb-1"></div>
    <div class="bg-orb orb-2"></div>
    <div class="bg-orb orb-3"></div>

    <header class="topbar">
        <div class="container topbar-inner">
            <div class="brand-wrap">
                @if($appLogoExists)
                    <img src="{{ asset($appLogoRelativePath) }}" alt="{{ __('ui.platform_logo_alt') }}" class="platform-logo">
                @endif
                <div class="brand-text">
                    <h1 class="brand">{{ __('ui.site_name') }}</h1>
                    <small class="brand-sub">{{ __('ui.platform_subtitle') }}</small>
                </div>
            </div>

            <div class="topbar-actions">
                <div class="lang-switch" aria-label="{{ __('ui.language') }}">
                    <a class="@if($currentLocale === 'ar') active @endif" href="{{ route('locale.switch', 'ar') }}">{{ __('ui.arabic') }}</a>
                    <a class="@if($currentLocale === 'en') active @endif" href="{{ route('locale.switch', 'en') }}">{{ __('ui.english') }}</a>
                </div>
                <a class="link-btn app-download-btn" href="{{ route('app.download.page') }}">{{ __('ui.download_app') }}</a>
                <button type="button" class="theme-toggle" data-theme-toggle>
                    <span data-theme-toggle-label>{{ __('ui.theme_dark') }}</span>
                </button>

                @auth
                    @php
                        $roleLabel = [
                            'developer' => __('ui.role_developer'),
                            'admin' => __('ui.role_admin'),
                            'data_entry' => __('ui.role_data_entry'),
                        ][auth()->user()->role] ?? auth()->user()->role;
                        $unreadNotifications = auth()->user()->unreadNotifications()->count();
                        $unreadMessagesCount = \App\Models\ChatMessage::query()
                            ->where('receiver_id', auth()->id())
                            ->whereNull('read_at')
                            ->count();
                    @endphp
                    <div class="user-chip">
                        <span>{{ auth()->user()->name }}</span>
                        <span class="badge badge-role">{{ $roleLabel }}</span>
                    </div>
                    <form class="logout-form" action="{{ route('logout') }}" method="POST">
                        @csrf
                        <button class="link-btn" type="submit">{{ __('ui.logout') }}</button>
                    </form>
                @endauth
            </div>
        </div>
    </header>

    @auth
        <nav class="nav">
            <div class="container nav-links">
                <a class="@if(request()->routeIs('dashboard')) active @endif" href="{{ route('dashboard') }}">{{ __('ui.dashboard') }}</a>

                @if(auth()->user()->isDeveloper())
                    <a class="@if(request()->routeIs('developers.administrators.*')) active @endif" href="{{ route('developers.administrators.index') }}">{{ __('ui.administrators') }}</a>
                    <a class="@if(request()->routeIs('developers.health.*')) active @endif" href="{{ route('developers.health.index') }}">{{ __('ui.health_check') }}</a>
                @endif

                @if(auth()->user()->isAdmin())
                    <a class="@if(request()->routeIs('admin.data-entry-users.*')) active @endif" href="{{ route('admin.data-entry-users.index') }}">{{ __('ui.data_entry_users') }}</a>
                @endif

                @if(auth()->user()->isDeveloper() || auth()->user()->isAdmin() || auth()->user()->isDataEntry())
                    @if(auth()->user()->hasPermission('manage_families'))
                    <a class="@if(request()->routeIs('families.*') || request()->routeIs('orphans.*')) active @endif" href="{{ route('families.index') }}">{{ __('ui.families') }}</a>
                    @if(Route::has('offline_queue.index'))
                        <a class="@if(request()->routeIs('offline_queue.*')) active @endif" href="{{ route('offline_queue.index') }}">{{ __('ui.offline_queue') }}</a>
                    @endif
                    @endif
                    <a class="@if(request()->routeIs('messages.*')) active @endif" href="{{ route('messages.index') }}">
                        {{ __('ui.messages') }}
                        @if($unreadMessagesCount > 0)
                            <span class="badge">{{ $unreadMessagesCount }}</span>
                        @endif
                    </a>
                @endif

                <span class="badge badge-queue" id="global-offline-queue-count" hidden></span>

                @if(auth()->user()->isDeveloper() || auth()->user()->isAdmin())
                    <a class="@if(request()->routeIs('benefits.*')) active @endif" href="{{ route('benefits.index') }}">{{ __('ui.benefits') }}</a>
                    @if(auth()->user()->hasPermission('view_activity_logs'))
                        <a class="@if(request()->routeIs('activity_logs.*')) active @endif" href="{{ route('activity_logs.index') }}">{{ __('ui.activity_logs') }}</a>
                    @endif
                @endif

                <a class="@if(request()->routeIs('notifications.*')) active @endif" href="{{ route('notifications.index') }}">
                    {{ __('ui.notifications') }}
                    @if($unreadNotifications > 0)
                        <span class="badge">{{ $unreadNotifications }}</span>
                    @endif
                </a>

                @if(auth()->user()->isDeveloper() || auth()->user()->isAdmin())
                    <a class="@if(request()->routeIs('developers.security.*') || request()->routeIs('admin.security.*')) active @endif"
                        href="{{ auth()->user()->isAdmin() ? route('admin.security.password.edit') : route('developers.security.password.edit') }}">
                        {{ __('ui.account_security') }}
                    </a>
                @endif

            </div>
        </nav>
    @endauth

    <main class="container main-content @yield('main_content_class')">
        @php
            $hidePageHead = trim((string) $__env->yieldContent('hide_page_head')) === '1';
            $hideGlobalAlerts = trim((string) $__env->yieldContent('hide_global_alerts')) === '1';
        @endphp
        @unless($hidePageHead)
            <section class="page-head">
                <h2>@yield('title', __('ui.site_name'))</h2>
                <p>{{ __('ui.page_subtitle') }}</p>
            </section>
        @endunless

        @unless($hideGlobalAlerts)
            @if(session('status'))
                <div class="alert success">{{ session('status') }}</div>
            @endif

            @if($errors->any())
                <div class="alert danger">
                    <ul>
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
        @endunless

        <div class="page-layout @yield('page_layout_class')">
            @yield('content')
        </div>
    </main>

    <footer class="footer">
        <div class="container footer-inner">
            <span>{{ __('ui.site_name') }}</span>
            <div class="social-links">
                <a href="https://wa.me/972599108541" target="_blank" rel="noopener noreferrer" aria-label="WhatsApp">{{ __('ui.social_whatsapp') }}</a>
                <a href="https://www.facebook.com/profile.php?id=100080141277004" target="_blank" rel="noopener noreferrer" aria-label="Facebook">{{ __('ui.social_facebook') }}</a>
                <a href="https://www.instagram.com/adld00/?__pwa=1" target="_blank" rel="noopener noreferrer" aria-label="Instagram">{{ __('ui.social_instagram') }}</a>
            </div>
            <span>{{ now()->format('Y') }} {{ __('ui.rights_reserved') }}</span>
        </div>
    </footer>

    <div id="page-scripts">
        @stack('scripts')
    </div>
</body>
</html>

