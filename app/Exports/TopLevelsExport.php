<?php

namespace App\Exports;

use App\Models\StudentEducation;
use App\Enums\Gender;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Facades\Excel;
use Carbon\Carbon;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Illuminate\Support\Facades\DB;

class TopLevelsExport implements FromCollection, WithHeadings, Responsable
{
    private string $fileName = 'top_levels.xlsx';
    protected array $filters;

    public function __construct(array $filters = [])
    {
        $this->filters = $filters;
    }

    public function collection(): Collection
    {
        $startDate = $this->filters['startDate'] ?? now()->startOfYear()->toDateTimeString();
        $endDate = $this->filters['endDate'] ?? now()->toDateTimeString();
        $cityIds = isset($this->filters['city']) ? implode(',', (array)$this->filters['city']) : null;
        $centerIds = isset($this->filters['center']) ? implode(',', (array)$this->filters['center']) : null;

        $cityCondition = $cityIds ? "AND s.street_id IN (SELECT id FROM hcsz_streets WHERE city_id IN ($cityIds))" : "";
        $centerCondition = $centerIds ? "AND sen.center_id IN ($centerIds)" : "";

        $rawSql = "
            SELECT 
                sed.level,
                COUNT(DISTINCT s.id) as total, 
                COUNT(DISTINCT CASE WHEN s.gender = 1 THEN s.id END) as males,
                COUNT(DISTINCT CASE WHEN s.gender = 2 THEN s.id END) as females,
                COUNT(DISTINCT CASE WHEN s.gender = 0 THEN s.id END) as unknown
            FROM hcsz_student_educations sed
            INNER JOIN hcsz_student_entries sen ON sed.student_id = sen.student_id 
                AND (DATE(sen.entry_time) >= DATE(sed.start_date))
                AND (sed.end_date IS NULL OR DATE(sen.entry_time) <= DATE(sed.end_date))
            LEFT JOIN hcsz_students AS s ON s.id = sed.student_id
            WHERE DATE(sen.entry_time) BETWEEN '$startDate' AND '$endDate'
            $cityCondition
            $centerCondition
            GROUP BY sed.level
            ORDER BY total DESC
            LIMIT 10
        ";

        $results = DB::select($rawSql);
        
        return collect($results)->map(function ($item) {
            return [
                'Level' => __('general.edu-levels.' . $item->level),
                'Total' => $item->total,
                'Males' => $item->males,
                'Females' => $item->females,
                'Unknown' => $item->unknown,
            ];
        });
    }

    public function headings(): array
    {
        return ['Level', 'Total', 'Males', 'Females', 'Unknown'];
    }

    public function toResponse($request): BinaryFileResponse
    {
        return Excel::download($this, $this->fileName);
    }
}