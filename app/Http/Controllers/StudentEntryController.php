<?php

namespace App\Http\Controllers;

use App\Models\Center;
use App\Models\Student;
use App\Models\StudentEntry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

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

    // public function store(Request $request, string $uuid)
    // {
    //     $student = Student::findOrFail($uuid);

    //     if (!$student->completed_data) {
    //         return redirect()->back()->with('error', '❌ لا يمكن إضافة دخولية: بيانات الطالب غير مكتملة.');
    //     }

    //     $validated = $request->validate([
    //         'entry_type' => 'required|in:0,1,2,3,4',
    //         'center_id' => 'required|exists:centers,id',
    //         'entry_time' => 'required',
    //         'use_drawer' => 'nullable|boolean',
    //         'use_printer' => 'nullable|boolean',
    //         'use_mecrator' => 'nullable|boolean',
    //         'use_plotter' => 'nullable|boolean',
    //               'use_internet' => 'nullable|boolean',
    //     ]);

    //     StudentEntry::create([
    //         'student_id' => $student->id,
    //         'user_id' => Auth::id(),
    //         'center_id' => $validated['center_id'],
    //         'entry_type' => $validated['entry_type'],
    //         'entry_time' => now(),
    //         'use_drawer' => $validated['use_drawer'] ?? false,
    //         'use_printer' => $validated['use_printer'] ?? false,
    //         'use_mecrator' => $validated['use_mecrator'] ?? false,
    //         'use_plotter' => $validated['use_plotter'] ?? false,
    //           'use_internet' => $validated['use_internet'] ?? false,
    //     ]);

    //     return redirect()->back()->with('success', 'تم تسجيل الدخولية بنجاح');
    // }

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

    $student = Student::findOrFail($studentId);

    // Calculate total from submitted array
    $totalAmount = 0;
    foreach ($data['services'] ?? [] as $service) {
        $totalAmount += ($service['price'] ?? 0) * ($service['quantity'] ?? 1);
    }

    // Add default service total if applicable
    $defaultService = \App\Helpers\ServiceHelper::getDefaultEntryService();
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
        \App\Models\EntryService::insert($entryServicesData);
    }

    // Insert default service
    if ($defaultService) {
        $exists = collect($data['services'] ?? [])->pluck('service_id')->contains($defaultService['id']);
        if (!$exists) {
            \App\Models\EntryService::create([
                'entry_id' => $entry->id,
                'service_id' => $defaultService['id'],
                'price_at_time' => $defaultService['price'],
                'quantity' => 1,
                'subtotal' => $defaultService['price'],
            ]);
        }
    }

    return redirect()->back()->with('success', 'تم تسجيل الدخولية بنجاح');
}
}
