<?php

namespace App\Http\Controllers\Admin;

use App\Models\Family;
use App\Models\Record;
use Illuminate\Http\Request;
use App\Exports\RecordsExport;
use Maatwebsite\Excel\Facades\Excel;
use App\Http\Controllers\Controller;

class ReportController extends Controller
{
    public function index()
    {
        $families = Family::with('benefits')->get();
        return view('admin.reports.index', compact('families'));
    }

    public function exportToExcel()
    {
        return Excel::download(new RecordsExport, 'records.xlsx');
    }

    public function generateStatistics()
    {
        $totalFamilies = Family::count();
        $totalBenefits = Benefit::count();
        $statistics = [
            'total_families' => $totalFamilies,
            'total_benefits' => $totalBenefits,
        ];

        return response()->json($statistics);
    }
}