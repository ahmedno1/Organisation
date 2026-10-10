@extends('layouts.app')

@section('title', __('ui.download_app'))
@section('page_layout_class', 'centered-page-layout')

@section('content')
    <div class="card app-download-card">
        <h2>{{ __('ui.download_app') }}</h2>
        <p class="muted">{{ __('ui.download_app_subtitle') }}</p>

        @if($apkAvailable)
            <div class="app-download-meta">
                <span class="badge">{{ __('ui.download_app_file') }}: {{ $apkName }}</span>
                <span class="badge">{{ __('ui.download_app_size') }}: {{ $apkSizeMb }} MB</span>
                <span class="badge">{{ __('ui.download_app_updated_at') }}: {{ $apkUpdatedAt }}</span>
            </div>

            <div class="app-download-actions">
                <a class="btn primary" href="{{ $downloadUrl }}">{{ __('ui.download_app_now') }}</a>
                <button type="button" class="btn" id="copy-download-link">{{ __('ui.copy_download_link') }}</button>
            </div>

            <div class="app-download-qr-wrap">
                <img src="{{ $qrUrl }}" alt="{{ __('ui.download_app_qr_alt') }}" class="app-download-qr">
                <small class="muted">{{ __('ui.download_app_qr_help') }}</small>
            </div>
        @else
            <div class="alert danger">{{ __('ui.download_app_not_available') }}</div>
        @endif
    </div>
@endsection

@push('scripts')
    <script>
        (() => {
            const copyBtn = document.getElementById('copy-download-link');
            if (!copyBtn) {
                return;
            }

            copyBtn.addEventListener('click', async () => {
                try {
                    await navigator.clipboard.writeText(@json($downloadUrl));
                    copyBtn.textContent = @json(__('ui.download_link_copied'));
                    setTimeout(() => {
                        copyBtn.textContent = @json(__('ui.copy_download_link'));
                    }, 1800);
                } catch (error) {
                    copyBtn.textContent = @json(__('ui.copy_download_link_failed'));
                    setTimeout(() => {
                        copyBtn.textContent = @json(__('ui.copy_download_link'));
                    }, 1800);
                }
            });
        })();
    </script>
@endpush
