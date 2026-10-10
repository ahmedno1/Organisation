@extends('layouts.app')

@section('title', __('ui.benefits'))

@section('content')
    <div class="card">
        <h2>{{ __('ui.benefits_management') }}</h2>

        <form method="POST" action="{{ route('benefits.store') }}" class="form-grid mt-16">
            @csrf
            <label>
                {{ __('ui.new_benefit_name') }}
                <input type="text" name="name" value="{{ old('name') }}" required>
            </label>
            <label>
                {{ __('ui.benefit_date') }}
                <input type="date" name="benefit_date" value="{{ old('benefit_date') }}">
            </label>
            <label>
                {{ __('ui.supporter_name') }}
                <input type="text" name="supporter_name" value="{{ old('supporter_name') }}">
            </label>
            <label>
                {{ __('ui.target_category') }}
                <input type="text" name="target_category" value="{{ old('target_category') }}">
            </label>
            <div>
                <button class="btn primary mt-16" type="submit">{{ __('ui.add') }}</button>
            </div>
        </form>
    </div>

    <div class="card mt-16">
        <table class="table">
            <thead>
                <tr>
                    <th>{{ __('ui.benefits') }}</th>
                    <th>{{ __('ui.benefit_date') }}</th>
                    <th>{{ __('ui.supporter_name') }}</th>
                    <th>{{ __('ui.target_category') }}</th>
                    <th>{{ __('ui.actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse($benefits as $benefit)
                    @php $editFormId = 'benefit-edit-'.$benefit->id; @endphp
                    <tr>
                        <td>
                            <div class="muted" style="font-size:.82rem; margin-bottom:.35rem;">
                                {{ $benefit->name }}
                                | داعم: {{ $benefit->supporter_name ?: '-' }}
                                | {{ optional($benefit->benefit_date)->format('Y-m-d') ?: '-' }}
                                | {{ $benefit->target_category ?: '-' }}
                            </div>
                            <input type="text" name="name" value="{{ $benefit->name }}" form="{{ $editFormId }}" required>
                        </td>
                        <td>
                            <input type="date" name="benefit_date" value="{{ optional($benefit->benefit_date)->format('Y-m-d') }}" form="{{ $editFormId }}">
                        </td>
                        <td>
                            <input type="text" name="supporter_name" value="{{ $benefit->supporter_name }}" form="{{ $editFormId }}">
                        </td>
                        <td>
                            <input type="text" name="target_category" value="{{ $benefit->target_category }}" form="{{ $editFormId }}">
                        </td>
                        <td>
                            <form method="POST" action="{{ route('benefits.update', $benefit) }}" id="{{ $editFormId }}">
                                @csrf
                                @method('PUT')
                                <button class="btn" type="submit">{{ __('ui.edit') }}</button>
                            </form>
                            <form method="POST" action="{{ route('benefits.destroy', $benefit) }}">
                                @csrf
                                @method('DELETE')
                                <button class="btn danger" type="submit" onclick="return confirm(@js(__('ui.delete_benefit_confirm')))">{{ __('ui.delete') }}</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5">{{ __('ui.no_benefits') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        {{ $benefits->links() }}
    </div>
@endsection
