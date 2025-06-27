<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class CustomEntriesExport implements FromCollection, WithHeadings
{
    protected Collection $data;

    public function __construct($data)
    {
        $this->data = collect($data);
    }

    public function collection(): Collection
    {
        return $this->data;
    }

    public function headings(): array
    {
        return [
            'اسم الطالب الثلاثي',
            'المدينة',
            'المركز',
            'نوع الدخول',
            'تاريخ الدخول',
            'خدمة طباعة',
            'خدمة مرسم',
            'خدمة ميكراتور',
            'المؤهل العلمي',
        ];
    }
}
