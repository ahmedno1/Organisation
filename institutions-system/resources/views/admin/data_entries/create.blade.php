@extends('layouts.app')

@section('content')
<div class="container">
    <h1 class="mb-4">Add Data Entry Personnel</h1>

    @if($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form action="{{ route('data_entries.store') }}" method="POST">
        @csrf
        <div class="mb-3">
            <label class="form-label" for="name">Name</label>
            <input class="form-control" id="name" name="name" type="text" value="{{ old('name') }}" required>
        </div>

        <div class="mb-3">
            <label class="form-label" for="data">Data</label>
            <textarea class="form-control" id="data" name="data" rows="4">{{ old('data') }}</textarea>
        </div>

        <button class="btn btn-primary" type="submit">Save</button>
        <a class="btn btn-secondary" href="{{ route('data_entries.index') }}">Cancel</a>
    </form>
</div>
@endsection
