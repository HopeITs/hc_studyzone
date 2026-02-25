<?php

namespace App\Exports;

use App\Enums\Gender;
use App\Models\Student;
use App\Models\StudentEducation;
use App\Models\StudentEntry;
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

        $entriesTable = (new StudentEntry())->getTable();
        $studentsTable = (new Student())->getTable();
        $studentEducationsTable = (new StudentEducation())->getTable();

        $results = StudentEducation::query()
            ->select("$studentEducationsTable.level")
            ->selectRaw("COUNT(DISTINCT $entriesTable.id) as total")
            ->selectRaw("SUM(CASE WHEN $studentsTable.gender = 1 THEN 1 ELSE 0 END) as males")
            ->selectRaw("SUM(CASE WHEN $studentsTable.gender = 2 THEN 1 ELSE 0 END) as females")
            ->selectRaw("SUM(CASE WHEN $studentsTable.gender = 0 THEN 1 ELSE 0 END) as unknown")
            ->join($studentsTable, "$studentsTable.id", '=', "$studentEducationsTable.student_id")
            ->join($entriesTable, function ($join) use ($entriesTable, $studentsTable, $studentEducationsTable) {
                $join->on("$studentsTable.id", '=', "$entriesTable.student_id")
                    ->whereRaw("$entriesTable.entry_time BETWEEN $studentEducationsTable.start_date AND COALESCE($studentEducationsTable.end_date, $entriesTable.entry_time)");
            })
            ->whereBetween("$entriesTable.entry_time", [$startDate, $endDate])
            ->when($cityIds, function ($q) use ($cityIds, $studentsTable) {
                $q->whereHas('student.street', function ($sq) use ($cityIds) {
                    $sq->whereIn('city_id', (array)$cityIds);
                });
            })
            ->when($centerIds, function ($q) use ($centerIds, $entriesTable) {
                $q->whereIn("$entriesTable.center_id", (array)$centerIds);
            })
            ->groupBy("$studentEducationsTable.level")
            ->orderByDesc('total')
            ->limit(10)
            ->get();

        return $results->map(function ($item) {
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
        return ['Education Level', 'Total Entries', 'Male Entries', 'Female Entries', 'Unknown Gender Entries'];
    }

    public function toResponse($request): BinaryFileResponse
    {
        return Excel::download($this, $this->fileName);
    }
}