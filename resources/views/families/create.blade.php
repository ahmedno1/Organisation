@extends('layouts.app')

@section('title', __('ui.add_family'))
@section('main_content_class', 'family-create-main')
@section('page_layout_class', 'family-create-layout')

@section('content')
    @php
        $emptyPixel = 'data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///ywAAAAAAQABAAACAUwAOw==';
    @endphp
    <div class="card institution-banner">
        <div class="institution-cell">
            <img
                id="selected-institution-logo"
                @if(optional(auth()->user()->institution)->logo_url)
                    src="{{ auth()->user()->institution->logo_url }}"
                @else
                    src="{{ $emptyPixel }}"
                @endif
                alt="{{ __('ui.institution_logo') }}"
                class="institution-logo"
                @if(!optional(auth()->user()->institution)->logo_url) style="display:none;" @endif
            >
            <div>
                <h3>{{ __('ui.institution') }}</h3>
                <p id="selected-institution-name">{{ auth()->user()->institution?->name ?? __('ui.not_specified') }}</p>
            </div>
        </div>
    </div>

    <div class="card family-form-card">
        <h2>{{ __('ui.enter_family_data') }}</h2>
        <form method="POST" action="{{ route('families.store') }}" class="form-grid family-entry-form" autocomplete="off" enctype="multipart/form-data">
            @csrf
            @include('families._form', ['submitLabel' => __('ui.save_record'), 'showSaveBenefitButton' => true])
        </form>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const institutionSelect = document.querySelector('select[name="institution_id"]');
            const nameEl = document.getElementById('selected-institution-name');
            const logoEl = document.getElementById('selected-institution-logo');
            const defaultName = @json(auth()->user()->institution?->name ?? __('ui.not_specified'));
            const defaultLogo = @json(auth()->user()->institution?->logo_url ?? '');
            const emptyLogo = @json($emptyPixel);

            if (!nameEl || !logoEl) {
                return;
            }

            function updateBannerFromSelection() {
                if (!institutionSelect) {
                    nameEl.textContent = defaultName;
                    if (defaultLogo) {
                        logoEl.src = defaultLogo;
                        logoEl.style.display = '';
                    } else {
                        logoEl.src = emptyLogo;
                        logoEl.style.display = 'none';
                    }
                    return;
                }

                const selected = institutionSelect.options[institutionSelect.selectedIndex];
                const selectedName = (selected?.dataset?.name || '').trim();
                const selectedLogo = (selected?.dataset?.logoUrl || '').trim();

                nameEl.textContent = selectedName || @json(__('ui.not_specified'));

                if (selectedLogo) {
                    logoEl.src = selectedLogo;
                    logoEl.style.display = '';
                } else {
                    logoEl.src = emptyLogo;
                    logoEl.style.display = 'none';
                }
            }

            institutionSelect?.addEventListener('change', updateBannerFromSelection);
            updateBannerFromSelection();
        });
    </script>
@endsection

