<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use App\Helpers\ServiceHelper;
use App\Models\Center;
use App\Models\EntryService;
use App\Models\Service;
use App\Models\Student;
use App\Models\StudentEntry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class StudentEntryController extends Controller
{
    public function showEntryForm(string $uuid)
    {
        $student = Student::findOrFail($uuid);

        if (!$student->completed_data) {
            return redirect()->route('filament.admin.resources.students.edit', $student)->with('error', '❌ بيانات الطالب غير مكتملة. الرجاء إكمال البيانات أولاً قبل تسجيل الدخولية.');
        }

        $entryTypes = collect(__('general.entry-types'))->toArray();
        $centers = Center::where('is_active', true)->orderBy('order')->get();
        return view('students.entry-form', compact('student', 'entryTypes', 'centers'));
    }

    public function store(Request $request, $studentId)
    {
        $data = $request->validate([
            'user_id' => 'required',
            'center_id' => 'required|exists:centers,id',
            'entry_type' => 'required',
            'entry_time' => 'required|date',
            'services' => 'nullable|array',
            'services.*.service_id' => 'required_with:services|exists:services,id',
            'services.*.price' => 'nullable|numeric',
            'services.*.quantity' => 'nullable|integer|min:1',
            'services.*.subtotal' => 'nullable|numeric',
        ]);

        if (Carbon::parse($data['entry_time'])->isAfter(today()->endOfDay())) {
            throw ValidationException::withMessages([
                'entry_time' => __('general.messages.future_entry_date_body'),
            ]);
        }

        $student = Student::findOrFail($studentId);

        // Calculate total from submitted array
        $totalAmount = 0;
        foreach ($data['services'] ?? [] as $service) {
            $totalAmount += ($service['price'] ?? 0) * ($service['quantity'] ?? 1);
        }

        // Add default service total if applicable
        $defaultService = ServiceHelper::getDefaultEntryService();
        if ($defaultService) {
            $totalAmount += $defaultService['price'];
        }

        $entry = StudentEntry::create([
            'student_id' => $student->id,
            'user_id' => $data['user_id'],
            'center_id' => $data['center_id'],
            'entry_type' => $data['entry_type'],
            'entry_time' => $data['entry_time'],
            'total_amount' => $totalAmount,
        ]);

        // Insert submitted services
        if (!empty($data['services'])) {
            $entryServicesData = [];
            foreach ($data['services'] as $service) {
                $price = (float) ($service['price'] ?? 0);
                $quantity = (int) ($service['quantity'] ?? 1);

                $entryServicesData[] = [
                    'entry_id' => $entry->id,
                    'service_id' => $service['service_id'],
                    'price_at_time' => $price,
                    'quantity' => $quantity,
                    'subtotal' => $price * $quantity,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
            EntryService::insert($entryServicesData);
        }

        // Insert default service
        if ($defaultService) {
            $exists = collect($data['services'] ?? [])->pluck('service_id')->contains($defaultService['id']);
            if (!$exists) {
                EntryService::create([
                    'entry_id' => $entry->id,
                    'service_id' => $defaultService['id'],
                    'price_at_time' => $defaultService['price'],
                    'quantity' => 1,
                    'subtotal' => $defaultService['price'],
                ]);
            }
        }

        // ─── NEW: Sync reference_column_name to student_entries ───

        // 1. Collect all attached service IDs
        $allServiceIds = collect($data['services'] ?? [])->pluck('service_id')->toArray();

        if ($defaultService && !in_array($defaultService['id'], $allServiceIds)) {
            $allServiceIds[] = $defaultService['id'];
        }

        // 2. Fetch the reference_column_name(s) for the attached services
        if (!empty($allServiceIds)) {
            $referenceColumns = Service::whereIn('id', $allServiceIds)
                ->whereNotNull('reference_column_name')
                ->pluck('reference_column_name')
                ->unique()
                ->filter()
                ->toArray();

            // 3. Prepare update array and update the entry
            $entryUpdates = [];
            foreach ($referenceColumns as $column) {
                $entryUpdates[$column] = true;
            }

            if (!empty($entryUpdates)) {
                $entry->update($entryUpdates);
            }
        }

        // ─── END NEW ───

        return redirect()->back()->with('success', 'تم تسجيل الدخولية بنجاح');
    }
}
