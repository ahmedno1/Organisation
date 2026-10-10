@extends('layouts.app')

@section('title', __('ui.add_administrator'))

@section('content')
    <div class="card">
        <h2>{{ __('ui.add_new_administrator') }}</h2>
        <form method="POST" action="{{ route('developers.administrators.store') }}" class="form-grid" enctype="multipart/form-data">
            @csrf
            @include('developer.administrators._form', ['submitLabel' => __('ui.save_administrator'), 'administrator' => null])
        </form>
    </div>
@endsection
