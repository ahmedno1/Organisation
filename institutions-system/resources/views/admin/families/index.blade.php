@extends('layouts.app')

@section('content')
<div class="container">
    <h1 class="mb-4">Families Management</h1>

    <div class="mb-3">
        <a href="{{ route('families.create') }}" class="btn btn-primary">Add Family</a>
        <a href="{{ route('families.export') }}" class="btn btn-success">Export to Excel</a>
    </div>

    <table class="table table-striped">
        <thead>
            <tr>
                <th>ID</th>
                <th>Head of Family</th>
                <th>Benefits</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @foreach($families as $family)
                <tr>
                    <td>{{ $family->id }}</td>
                    <td>{{ $family->head_of_family }}</td>
                    <td>
                        @foreach($family->benefits as $benefit)
                            <span class="badge bg-info">{{ $benefit->name }}</span>
                        @endforeach
                    </td>
                    <td>
                        <a href="{{ route('families.edit', $family->id) }}" class="btn btn-warning btn-sm">Edit</a>
                        <form action="{{ route('families.destroy', $family->id) }}" method="POST" style="display:inline;">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                        </form>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    {{ $families->links() }}
</div>
@endsection

@section('styles')
<style>
    .badge {
        margin-right: 5px;
    }
</style>
@endsection