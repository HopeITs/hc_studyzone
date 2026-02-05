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
            return redirect()->back()
                ->with('error', '❌ لا يمكن إضافة دخولية: بيانات الطالب غير مكتملة. الرجاء مراجعة البيانات المطلوبة أولاً.');
        }

        $entryTypes = collect(__('general.entry-types'))->toArray();
        $centers = Center::where('is_active',true)->orderBy('order')->get();
        return view('students.entry-form', compact('student', 'entryTypes', 'centers'));
    }

    public function store(Request $request, string $uuid)
    {
        $student = Student::findOrFail($uuid);

        if (!$student->completed_data) {
            return redirect()->back()
                ->with('error', '❌ لا يمكن إضافة دخولية: بيانات الطالب غير مكتملة.');
        }

        $validated = $request->validate([

            'entry_type' => 'required|in:0,1,2,3,4',
            'center_id' => 'required|exists:centers,id',
            'entry_time' => 'required',
            'use_drawer' => 'nullable|boolean',
            'use_printer' => 'nullable|boolean',
            'use_mecrator' => 'nullable|boolean',
            'use_plotter' => 'nullable|boolean',
        ]);


        StudentEntry::create([
            'student_id' => $student->id,
            'user_id' => Auth::id(),
            'center_id' => $validated['center_id'],
            'entry_type' => $validated['entry_type'],
            'entry_time' => now(),
            'use_drawer' => $validated['use_drawer'] ?? false,
            'use_printer' => $validated['use_printer'] ?? false,
            'use_mecrator' => $validated['use_mecrator'] ?? false,
            'use_plotter' => $validated['use_plotter'] ?? false,
        ]);

        return redirect()->back()->with('success', 'تم تسجيل الدخولية بنجاح');
    }
}
