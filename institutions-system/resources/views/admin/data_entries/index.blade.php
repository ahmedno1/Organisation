@extends('layouts.app')

@section('content')
<div class="container">
    <h1 class="mb-4">Data Entry Personnel</h1>
    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    <div class="mb-3">
        <a href="{{ route('data_entries.create') }}" class="btn btn-primary">Add Data Entry Personnel</a>
    </div>
    <table class="table table-striped">
        <thead>
            <tr>
                <th>ID</th>
                <th>Name</th>
                <th>Data</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse($dataEntries as $dataEntry)
                <tr>
                    <td>{{ $dataEntry->id }}</td>
                    <td>{{ $dataEntry->name }}</td>
                    <td>{{ $dataEntry->data }}</td>
                    <td>
                        <a href="{{ route('data_entries.edit', $dataEntry->id) }}" class="btn btn-warning">Edit</a>
                        <form action="{{ route('data_entries.destroy', $dataEntry->id) }}" method="POST" style="display:inline;">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-danger">Delete</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="4">No data entry personnel found.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
    {{ $dataEntries->links() }}
</div>
@endsection

@section('scripts')
<script>
    // Additional JavaScript can be added here
</script>
@endsection
