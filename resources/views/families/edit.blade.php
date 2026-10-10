@extends('layouts.app')

@section('title', __('ui.edit_family'))

@section('content')
    <div class="card institution-banner">
        <div class="institution-cell">
            @if(optional(auth()->user()->institution)->logo_url)
                <img
                    src="{{ auth()->user()->institution->logo_url }}"
                    alt="{{ __('ui.institution_logo') }}"
                    class="institution-logo"
                >
            @endif
            <div>
                <h3>{{ __('ui.institution') }}</h3>
                <p>{{ auth()->user()->institution?->name ?? __('ui.not_specified') }}</p>
            </div>
        </div>
    </div>

    <div class="card family-form-card">
        <h2>{{ __('ui.edit_family_data') }}</h2>
        <form method="POST" action="{{ route('families.update', $family) }}" class="form-grid family-entry-form" autocomplete="off" enctype="multipart/form-data">
            @csrf
            @method('PUT')
            @include('families._form', ['submitLabel' => __('ui.update_record')])
        </form>
    </div>

    @if($hasFamilyAttachmentsTable ?? false)
        <div class="card mt-16">
            <h3>{{ __('ui.family_and_members_attachments') }}</h3>

            <form method="POST" action="{{ route('families.attachments.store', $family) }}" enctype="multipart/form-data" class="actions" data-offline-queue="enabled">
                @csrf
                <select name="member_id">
                    <option value="">{{ __('ui.family_general_attachment') }}</option>
                    @foreach($family->members as $member)
                        <option value="{{ $member->id }}">{{ $member->name }}{{ $member->relation ? ' - '.$member->relation : '' }}</option>
                    @endforeach
                </select>
                <input type="text" name="name" placeholder="{{ __('ui.attachment_name_optional') }}">
                <input type="file" name="attachment" accept="image/*,.pdf,.doc,.docx,.xls,.xlsx,.txt" required>
                <button class="btn primary" type="submit">{{ __('ui.upload') }}</button>
            </form>

            <table class="table mt-16">
                <thead>
                    <tr>
                        <th>{{ __('ui.attachment_name') }}</th>
                        <th>{{ __('ui.type') }}</th>
                        <th>{{ __('ui.linked_to') }}</th>
                        <th>{{ __('ui.size') }}</th>
                        <th>{{ __('ui.uploader') }}</th>
                        <th>{{ __('ui.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($family->attachments as $attachment)
                        <tr>
                            <td>{{ $attachment->name }}</td>
                            <td>{{ $attachment->member ? __('ui.member_attachment') : __('ui.family_attachment') }}</td>
                            <td>{{ $attachment->member?->name ?? __('ui.family') }}</td>
                            <td>{{ number_format($attachment->size / 1024, 1) }} KB</td>
                            <td>{{ $attachment->uploader?->name ?? __('ui.dash') }}</td>
                            <td class="actions">
                                <a class="btn" data-spa="false" href="{{ route('families.attachments.download', [$family, $attachment]) }}">{{ __('ui.download') }}</a>
                                <form method="POST" action="{{ route('families.attachments.destroy', [$family, $attachment]) }}">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn danger" type="submit">{{ __('ui.delete') }}</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6">{{ __('ui.no_attachments_yet') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endif

    <div class="card mt-16">
        <h3>{{ __('ui.family_timeline') }}</h3>
        <table class="table">
            <thead>
                <tr>
                    <th>{{ __('ui.event') }}</th>
                    <th>{{ __('ui.user') }}</th>
                    <th>{{ __('ui.time') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse($timeline as $log)
                    <tr>
                        <td>{{ $log->action }}</td>
                        <td>{{ $log->user?->name ?? 'system' }}</td>
                        <td>{{ optional($log->created_at)->format('Y-m-d H:i') }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="3">{{ __('ui.no_timeline_yet') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection

