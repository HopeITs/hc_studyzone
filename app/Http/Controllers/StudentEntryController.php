<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use App\Helpers\ServiceHelper;
use App\Models\Center;
use App\Enums\Roles;
use App\Enums\UserPermission;
use App\Filament\Resources\StudentResource;
use App\Models\LockedMonth;
use App\Models\ServicePrice;
use App\Models\Service;
use App\Models\Student;
use App\Models\StudentEntry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

    public function store(Request $request, string $studentId)
    {
        $user = $request->user();
        abort_unless($user->is_active && $user->hasPermission(UserPermission::CreateEntry)
            && $user->hasPermission(UserPermission::ViewStudents), 403);

        $data = $request->validate([
            'center_id' => 'required|integer|exists:centers,id',
            'entry_type' => 'required|integer',
            'entry_time' => 'required|date',
            'services' => 'nullable|array',
            'services.*.service_id' => 'required|integer|exists:services,id',
            'services.*.quantity' => 'nullable|integer|min:1',
        ]);

        $centerId = (int) $data['center_id'];
        $allowed = match ($user->role) {
            Roles::GeneralAdmin() => true,
            Roles::CityAdmin() => Center::query()->whereKey($centerId)->where('city_id', $user->city_id)->exists(),
            Roles::CenterAdmin() => (int) $user->center_id === $centerId,
            default => false,
        };
        abort_unless($allowed, 403);

        $date = Carbon::parse($data['entry_time']);
        if ($date->isAfter(today()->endOfDay()) || LockedMonth::isLocked($date)) {
            throw ValidationException::withMessages([
                'entry_time' => $date->isAfter(today()->endOfDay())
                    ? __('general.messages.future_entry_date_body')
                    : __('general.messages.month_locked_body'),
            ]);
        }

        $student = StudentResource::getEloquentQuery()->findOrFail($studentId);
        $services = collect($data['services'] ?? [])->keyBy('service_id');
        $defaultService = ServiceHelper::getDefaultEntryService();

        if ($defaultService && ! $services->has($defaultService['id'])) {
            $services->put($defaultService['id'], ['quantity' => 1]);
        }

        $prices = ServicePrice::activePricesFor($centerId, $services->keys()->all());
        if (! ServiceHelper::ensureServicesPriced($services->keys()->all(), $prices)) {
            return redirect()->back();
        }

        DB::transaction(function () use ($student, $user, $centerId, $data, $services, $prices): void {
            $entry = StudentEntry::create([
                'student_id' => $student->id,
                'user_id' => $user->id,
                'center_id' => $centerId,
                'entry_type' => $data['entry_type'],
                'entry_time' => $data['entry_time'],
            ]);

            foreach ($services as $id => $service) {
                $amount = (int) ($service['quantity'] ?? 1);
                $entry->services()->create([
                    'service_id' => $id,
                    'amount' => $amount,
                    'price_at_time' => $prices[$id],
                    'total' => $prices[$id] * $amount,
                ]);
            }

            $references = Service::whereIn('id', $services->keys())->pluck('reference_column_name')->filter();
            $entry->update(array_fill_keys($references->all(), true));
        }, 3);

        return redirect()->back()->with('success', __('general.labels.entry_recorded_successfully'));
    }
}
