<?php

namespace App\Filament\Resources\StudentResource\Pages;

use App\Filament\Resources\StudentResource;
use App\Models\StudentEntry;
use Filament\Resources\Pages\Page;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;

class ViewStudentEntry extends Page
{
    use InteractsWithRecord;

    protected static string $resource = StudentResource::class;

    protected string $view = 'filament.resources.student-resource.pages.view-student-entry';

    public StudentEntry $entry;

    public function mount(int|string $record, int|string $entry): void
    {
        // Resolve the Student record
        $this->record = $this->resolveRecord($record);

        // Resolve the Entry record
        $this->entry = StudentEntry::with(['services.service', 'center', 'user'])->findOrFail($entry);

        // Security: Ensure the entry belongs to this student
        abort_if($this->entry->student_id !== $this->record->id, 404);
    }

    public function getHeading(): string
    {
        return 'فاتورة دخولية';
    }

    public function getSubheading(): ?string
    {
        return 'الطالب: ' . $this->record->name;
    }

    public function getBreadcrumbs(): array
    {
        return [
            StudentResource::getUrl('index') => 'الطلاب',
            StudentResource::getUrl('view', ['record' => $this->record]) => $this->record->name,
            'فاتورة رقم #' . $this->entry->id,
        ];
    }
}
