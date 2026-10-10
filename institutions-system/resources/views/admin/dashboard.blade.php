@extends('layouts.app')

@section('content')
<div class="container">
    <h1 class="text-center">Administrator Dashboard</h1>

    <div class="row mb-4">
        <div class="col-md-6">
            <h2>Manage Administrators</h2>
            <a href="{{ route('administrators.index') }}" class="btn btn-primary">View Administrators</a>
            <a href="{{ route('administrators.create') }}" class="btn btn-success">Add Administrator</a>
        </div>
        <div class="col-md-6">
            <h2>Manage Data Entry Personnel</h2>
            <a href="{{ route('data_entries.index') }}" class="btn btn-primary">View Data Entry Personnel</a>
            <a href="{{ route('data_entries.create') }}" class="btn btn-success">Add Data Entry Personnel</a>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-md-6">
            <h2>Generate Reports</h2>
            <a href="{{ route('reports.index') }}" class="btn btn-primary">View Reports</a>
        </div>
        <div class="col-md-6">
            <h2>Family Benefits</h2>
            <a href="{{ route('families.index') }}" class="btn btn-primary">Manage Family Benefits</a>
        </div>
    </div>

    <div class="row">
        <div class="col-md-12">
            <h2>Statistics</h2>
            <p>Here you can view various statistics related to the institutions and data entries.</p>
            <!-- Add statistics display here -->
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
    // Add any specific JavaScript for the dashboard here
</script>
@endsection

<style>
    /* Improved color formatting */
    .btn-primary {
        background-color: #007bff;
        border-color: #007bff;
    }
    .btn-success {
        background-color: #28a745;
        border-color: #28a745;
    }
</style>