<?php

namespace App\Exports;

use App\Models\Record;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class RecordsExport implements FromCollection, WithHeadings
{
    public function collection()
    {
        return Record::all();
    }

    public function headings(): array
    {
        return [
            'ID',
            'Family ID',
            'Data',
            'Created At',
            'Updated At',
        ];
    }
}