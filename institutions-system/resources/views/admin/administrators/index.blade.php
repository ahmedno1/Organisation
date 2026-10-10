@extends('layouts.app')

@section('content')
<div class="container">
    <h1 class="mb-4">Administrators</h1>
    <div class="mb-3">
        <a href="{{ route('administrators.create') }}" class="btn btn-primary">Add Administrator</a>
    </div>
    <table class="table table-striped">
        <thead>
            <tr>
                <th>ID</th>
                <th>Name</th>
                <th>Email</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @foreach($administrators as $administrator)
                <tr>
                    <td>{{ $administrator->id }}</td>
                    <td>{{ $administrator->name }}</td>
                    <td>{{ $administrator->email }}</td>
                    <td>
                        <a href="{{ route('administrators.edit', $administrator->id) }}" class="btn btn-warning">Edit</a>
                        <form action="{{ route('administrators.destroy', $administrator->id) }}" method="POST" style="display:inline;">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-danger">Delete</button>
                        </form>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endsection