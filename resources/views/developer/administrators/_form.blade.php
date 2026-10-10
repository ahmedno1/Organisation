@php
    $administrator = $administrator ?? null;
    $currentInstitution = optional($administrator)->institution;
    $emptyPixel = 'data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///ywAAAAAAQABAAACAUwAOw==';
@endphp

<div class="form-grid two-cols">
    <label>
        {{ __('ui.administrator_name') }}
        <input type="text" name="name" value="{{ old('name', optional($administrator)->name) }}" required>
    </label>

    <label>
        {{ __('ui.email') }}
        <input type="email" name="email" value="{{ old('email', optional($administrator)->email) }}" required>
    </label>

    <label>
        {{ __('ui.select_existing_institution_optional') }}
        <select name="institution_id">
            <option value="">{{ __('ui.create_new_institution_hint') }}</option>
            @foreach($institutions as $institution)
                <option value="{{ $institution->id }}" @selected((string) old('institution_id', optional($administrator)->institution_id) === (string) $institution->id)>
                    {{ $institution->name }}
                </option>
            @endforeach
        </select>
    </label>

    <label>
        {{ __('ui.new_institution_name_optional') }}
        <input type="text" name="institution_name" value="{{ old('institution_name', optional($currentInstitution)->name) }}">
    </label>

    <label>
        {{ __('ui.institution_storage_quota_mb') }}
        <input
            type="number"
            name="institution_storage_quota_mb"
            min="1"
            step="1"
            value="{{ old('institution_storage_quota_mb', optional($currentInstitution)->storage_quota_mb) }}"
        >
        <small class="field-help">{{ __('ui.institution_storage_quota_hint') }}</small>
    </label>

    <label>
        {{ __('ui.institution_logo_optional') }}
        <input type="file" name="institution_logo" id="institution_logo_input" accept=".png,.jpg,.jpeg,.gif,.webp,.svg,.avif,.ico,image/*">
        <small class="field-help">{{ __('ui.logo_allowed_types_and_size') }}</small>
        <small class="field-help danger" id="logo_validation_error" style="display:none;"></small>
        <button class="btn" type="button" id="preview_logo_btn">{{ __('ui.preview_logo') }}</button>
        <div id="logo_preview_box" style="display:none;">
            <small class="field-help">{{ __('ui.logo_preview') }}:</small>
            <img src="{{ $emptyPixel }}" alt="{{ __('ui.institution_logo') }}" class="institution-logo-preview" id="logo_preview_img">
        </div>
        @if(optional($currentInstitution)->logo_url)
            <small class="field-help">{{ __('ui.current_logo') }}:</small>
            <img
                src="{{ $currentInstitution->logo_url }}"
                alt="{{ __('ui.institution_logo') }}"
                class="institution-logo-preview"
            >
        @endif
    </label>

    <label>
        {{ __('ui.password') }} {{ isset($administrator) ? __('ui.leave_blank_no_change') : '' }}
        <input type="password" name="password" {{ isset($administrator) ? '' : 'required' }}>
    </label>
</div>

<button class="btn primary" type="submit">{{ $submitLabel }}</button>

@push('scripts')
    <script>
        (() => {
            const fileInput = document.getElementById('institution_logo_input');
            const previewBtn = document.getElementById('preview_logo_btn');
            const previewBox = document.getElementById('logo_preview_box');
            const previewImg = document.getElementById('logo_preview_img');
            const validationError = document.getElementById('logo_validation_error');
            const allowedExtensions = ['png', 'jpg', 'jpeg', 'gif', 'webp', 'svg', 'avif', 'ico'];
            const maxSizeBytes = 5 * 1024 * 1024;
            const emptyLogo = @json($emptyPixel);

            if (!fileInput || !previewBtn || !previewBox || !previewImg || !validationError) {
                return;
            }

            const clearError = () => {
                validationError.textContent = '';
                validationError.style.display = 'none';
            };

            const setError = (message) => {
                validationError.textContent = message;
                validationError.style.display = '';
            };

            const validateFile = (file) => {
                if (!file) {
                    clearError();
                    return true;
                }

                const extension = (file.name.split('.').pop() || '').toLowerCase();
                const hasAllowedMime = typeof file.type === 'string' && file.type.startsWith('image/');
                const hasAllowedExtension = allowedExtensions.includes(extension);
                if (!hasAllowedMime && !hasAllowedExtension) {
                    setError('{{ __("ui.logo_invalid_type") }}');
                    return false;
                }

                if (file.size > maxSizeBytes) {
                    setError('{{ __("ui.logo_invalid_size") }}');
                    return false;
                }

                clearError();
                return true;
            };

            const renderPreview = () => {
                const file = fileInput.files?.[0];
                if (!file) {
                    previewBox.style.display = 'none';
                    previewImg.src = emptyLogo;
                    clearError();
                    return;
                }

                if (!validateFile(file)) {
                    previewBox.style.display = 'none';
                    previewImg.src = emptyLogo;
                    return;
                }

                const url = URL.createObjectURL(file);
                previewImg.src = url;
                previewBox.style.display = '';
            };

            previewBtn.addEventListener('click', renderPreview);
            fileInput.addEventListener('change', () => {
                const file = fileInput.files?.[0];
                if (!validateFile(file)) {
                    previewBox.style.display = 'none';
                    previewImg.src = emptyLogo;
                    return;
                }

                renderPreview();
            });
        })();
    </script>
@endpush
