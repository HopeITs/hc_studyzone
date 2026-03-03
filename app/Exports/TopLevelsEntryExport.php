<?php

namespace App\Exports;

use App\Models\StudentEducation;
use App\Enums\Gender;
use Illuminate\Contracts\Support\Responsable;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Facades\Excel;
use Carbon\Carbon;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class TopLevelsEntryExport implements FromCollection, WithHeadings, Responsable
{
    private string $fileName = 'top_levels_by_entries.xlsx';
    protected array $filters;

    public function construct(array $filters = [])
    {
        $this->filters = $filters;
    }

    public function collection()
    {
        $startDate = isset($this->filters['startDate'])
            ? Carbon::parse($this->filters['startDate'])->startOfDay()
            : now()->startOfYear()->startOfDay();

        $endDate = isset($this->filters['endDate'])
            ? Carbon::parse($this->filters['endDate'])->endOfDay()
            : now()->endOfMonth()->endOfDay();

        $cityIds = $this->filters['city'] ?? null;
        $centerIds = $this->filters['center'] ?? null;

        return StudentEducation::query()
            ->select('student_educations.level')
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('SUM(CASE WHEN gender = ? THEN 1 ELSE 0 END) as males', [Gender::Male()])
            ->selectRaw('SUM(CASE WHEN gender = ? THEN 1 ELSE 0 END) as females', [Gender::Female()])
            ->selectRaw('SUM(CASE WHEN gender = ? THEN 1 ELSE 0 END) as unknown', [Gender::Unknown()])
            ->whereNull('student_educations.end_date')
            ->join('students', 'student_educations.student_id', '=', 'students.id')
            ->join('student_entries', 'students.id', '=', 'student_entries.student_id')
            ->whereBetween('student_entries.entry_time', [$startDate, $endDate])
            ->when($cityIds, function ($q) use ($cityIds) {
                return $q->whereIn('students.street_id', function ($sub) use ($cityIds) {
                    $sub->select('id')->from('streets')->whereIn('city_id', (array)$cityIds);
                });
            })
            ->when($centerIds, fn($q) => $q->whereIn('student_entries.center_id', (array)$centerIds))
            ->groupBy('student_educations.level')
            ->orderByDesc('total')
            ->limit(10)
            ->get()
            ->map(function ($item) {
                return [
                    'Level' => ("general.edu-levels.{$item->level}"),
                    'Total Entries' => $item->total,
                    'Males' => $item->males,
                    'Females' => $item->females,
                    'Unknown' => $item->unknown,
                ];
            });
    }

    public function headings(): array
    {
        return ['Level', 'Total Entries', 'Males', 'Females', 'Unknown'];
    }

    public function toResponse($request): BinaryFileResponse
    {
        return Excel::download($this, $this->fileName);
    }
}