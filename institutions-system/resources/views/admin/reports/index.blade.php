@extends('layouts.app')

@section('content')
<div class="container">
    <h1 class="mb-4">Reports</h1>

    <div class="row mb-4">
        <div class="col-md-6">
            <form action="{{ route('reports.generate') }}" method="GET">
                <div class="form-group">
                    <label for="date_range">Select Date Range:</label>
                    <input type="text" class="form-control" id="date_range" name="date_range" placeholder="YYYY-MM-DD to YYYY-MM-DD" required>
                </div>
                <button type="submit" class="btn btn-primary mt-3">Generate Report</button>
            </form>
        </div>
        <div class="col-md-6 text-right">
            <a href="{{ route('reports.export') }}" class="btn btn-success mt-3">Export to Excel</a>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h5>Statistics</h5>
        </div>
        <div class="card-body">
            <p>Total Families: {{ $totalFamilies }}</p>
            <p>Total Benefits: {{ $totalBenefits }}</p>
            <p>Total Records: {{ $totalRecords }}</p>
        </div>
    </div>

    <div class="card mt-4">
        <div class="card-header">
            <h5>Family Benefits</h5>
        </div>
        <div class="card-body">
            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th>Family Head</th>
                        <th>Benefits</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($families as $family)
                        <tr>
                            <td>{{ $family->head_of_family }}</td>
                            <td>{{ implode(', ', $family->benefits->pluck('name')->toArray()) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
    $(function() {
        $('#date_range').daterangepicker({
            opens: 'left'
        });
    });
</script>
@endsection