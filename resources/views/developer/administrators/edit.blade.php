@extends('layouts.app')

@section('title', __('ui.edit_administrator'))

@section('content')
    <div class="card">
        <h2>{{ __('ui.edit_administrator_data') }}</h2>
        <form method="POST" action="{{ route('developers.administrators.update', $administrator) }}" class="form-grid" enctype="multipart/form-data">
            @csrf
            @method('PUT')
            @include('developer.administrators._form', ['submitLabel' => __('ui.update_administrator')])
        </form>
    </div>
@endsection
