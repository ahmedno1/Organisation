@php
    $dataEntryUser = $dataEntryUser ?? null;
    $institution = auth()->user()->institution;
    $defaultPermissions = $dataEntryUser?->defaultPermissions() ?? [
        'manage_families' => true,
        'view_all_families' => true,
        'edit_families' => true,
        'delete_families' => false,
        'export_reports' => false,
        'import_families' => false,
    ];
    $permissionOptions = [
        'manage_families' => __('ui.permission_manage_families'),
        'view_all_families' => __('ui.permission_view_all_families'),
        'edit_families' => __('ui.permission_edit_families'),
        'delete_families' => __('ui.permission_delete_families'),
        'export_reports' => __('ui.permission_export_reports'),
        'import_families' => __('ui.permission_import_families'),
    ];
@endphp

<div class="form-grid two-cols">
    <label>
        {{ __('ui.institution') }}
        <div class="institution-input">
            @if(optional($institution)->logo_url)
                <img
                    src="{{ $institution->logo_url }}"
                    alt="Institution Logo"
                    class="institution-logo"
                >
            @endif
            <input type="text" value="{{ $institution?->name ?? __('ui.not_specified') }}" readonly>
        </div>
    </label>

    <label>
        {{ __('ui.name') }}
        <input type="text" name="name" value="{{ old('name', optional($dataEntryUser)->name) }}" required>
    </label>

    <label>
        {{ __('ui.email') }}
        <input type="email" name="email" value="{{ old('email', optional($dataEntryUser)->email) }}" required>
    </label>

    <label>
        {{ __('ui.password') }} {{ isset($dataEntryUser) ? '('.__('ui.optional').')' : '' }}
        <input type="password" name="password" {{ isset($dataEntryUser) ? '' : 'required' }}>
    </label>
</div>

<div class="form-grid two-cols">
    <label class="full-width">{{ __('ui.simple_permissions_for_data_entry') }}</label>
    @foreach($permissionOptions as $permissionKey => $permissionLabel)
        <label class="checkbox-row">
            <input
                type="checkbox"
                name="permissions[{{ $permissionKey }}]"
                value="1"
                @checked(old("permissions.$permissionKey", $dataEntryUser?->hasPermission($permissionKey) ?? ($defaultPermissions[$permissionKey] ?? false)))
            >
            <span>{{ $permissionLabel }}</span>
        </label>
    @endforeach
</div>

<button class="btn primary" type="submit">{{ $submitLabel }}</button>

