<?php

namespace App\Http\Controllers;

use App\Models\Student;
use App\Models\StudentEntry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class StudentEntryController extends Controller
{
    public function showEntryForm(string $uuid)
    {
        $student = Student::findOrFail($uuid);
        $entryTypes = collect(__('general.entry-types'))->toArray();

        return view('students.entry-form', compact('student', 'entryTypes'));
    }

    public function store(Request $request, string $uuid)
    {
        $student = Student::findOrFail($uuid);

        $validated = $request->validate([
            'entry_type' => 'required|in:0,1,2',
        ]);

        StudentEntry::create([
            'student_id' => $student->id,
            'user_id' => Auth::id(),
            'center_id' => Auth::user()->center_id ?? 1,
            'entry_type' => $validated['entry_type'],
            'entry_time' => now(),
        ]);

        return redirect()->back()->with('success', 'Entry recorded successfully.');
    }
}
