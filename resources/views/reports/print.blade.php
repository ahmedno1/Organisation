<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="UTF-8">
    <title>{{ __('ui.printable_report') }}</title>
    <style>
        body { font-family: Tahoma, Arial, sans-serif; margin: 24px; }
        h1 { margin: 0 0 16px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #bbb; padding: 8px; text-align: right; }
        th { background: #f0f0f0; }
        .actions { margin: 0 0 12px; }
        @media print { .actions { display: none; } }
    </style>
</head>
<body>
<div class="actions">
    <button onclick="window.print()">{{ __('ui.print_pdf') }}</button>
</div>
<h1>{{ __('ui.widows_report') }}</h1>
<table>
    <thead>
    <tr>
        <th>{{ __('ui.name') }}</th>
        <th>{{ __('ui.identity') }}</th>
        <th>{{ __('ui.marital_distribution') }}</th>
        <th>{{ __('ui.family_members_total') }}</th>
        <th>{{ __('ui.registered_children_count') }}</th>
        <th>{{ __('ui.institution') }}</th>
    </tr>
    </thead>
    <tbody>
    @forelse($families as $family)
        <tr>
            <td>{{ $family->head_name }}</td>
            <td>{{ $family->head_national_id }}</td>
            <td>{{ $family->marital_status }}</td>
            <td>{{ $family->family_members_count }}</td>
            <td>{{ $family->members_count }}</td>
            <td>{{ $family->institution?->name }}</td>
        </tr>
    @empty
        <tr><td colspan="6">{{ __('ui.no_matching_results') }}</td></tr>
    @endforelse
    </tbody>
</table>
</body>
</html>
