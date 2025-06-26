<?php

namespace App\Exports;

use App\Enums\Gender;
use App\Models\StudentEducation;
use Carbon\Carbon;
use Illuminate\Contracts\Support\Responsable;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class TopLevelsExport implements FromCollection, WithHeadings, Responsable
{
    private string $fileName = 'top_levels.xlsx';
    protected array $filters;

    public function __construct(array $filters = [])
    {
        $this->filters = $filters;
    }

    public function collection()
    {
        $startDate = isset($this->filters['startDate'])
            ? Carbon::parse($this->filters['startDate'])->startOfDay()
            : now()->startOfMonth()->startOfDay();

        $endDate = isset($this->filters['endDate'])
            ? Carbon::parse($this->filters['endDate'])->endOfDay()
            : now()->endOfMonth()->endOfDay();

        $cityIds = $this->filters['city'] ?? null;
        $centerIds = $this->filters['center'] ?? null;

        $query = StudentEducation::query()
            ->selectRaw('
                level,
                COUNT(*) as total,
                SUM(CASE WHEN gender = ? THEN 1 ELSE 0 END) as males,
                SUM(CASE WHEN gender = ? THEN 1 ELSE 0 END) as females,
                SUM(CASE WHEN gender = ? THEN 1 ELSE 0 END) as unknown
            ', [Gender::Male(), Gender::Female(), Gender::Unknown()])
            ->join('students', 'student_educations.student_id', '=', 'students.id')
            ->when($startDate, fn($q) => $q->where('students.created_at', '>=', $startDate))
            ->when($endDate, fn($q) => $q->where('students.created_at', '<=', $endDate))
            ->when($cityIds, fn($q) => $q->whereHas('student.street', fn($s) => $s->whereIn('city_id', $cityIds)))
            ->when($centerIds, fn($q) => $q->whereHas('student.entries', fn($e) => $e->whereIn('center_id', $centerIds)))
            ->groupBy('level')
            ->having('total', '>', 0)
            ->orderByDesc('total')
            ->limit(10)
            ->get();

        return $query->map(function ($item) {
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
