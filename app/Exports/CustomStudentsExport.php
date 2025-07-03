<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class CustomStudentsExport implements FromCollection, WithHeadings
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
            'محافظة',
            'رابط الاونلاين',
            'رابط الاوفلاين',
            'اختصاص دراسي',
        ];
    }
}
