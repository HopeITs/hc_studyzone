<?php

namespace App\Models;

use App\Traits\ActivityLog;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasName;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Enums\Nationality;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Student extends Model implements HasName
{
    use HasUuids, HasFactory, ActivityLog;

    public $incrementing = false;
    protected $keyType = 'string';
    protected $guarded = ['is_child', 'city_id'];
    protected $primaryKey = 'id';

    protected $casts = [
        'nationality' => Nationality::class,
        'national_number' => 'string',
        'foreign_id_number' => 'string',
        'completed_data' => 'boolean',
        'skip_national_number' => 'boolean',
    ];

    protected static function boot()
    {
        parent::boot();

        static::saving(function (Student $student) {
            $student->completed_data = empty($student->getEntryValidationErrors());
        });
    }

    public function entries(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(StudentEntry::class);
    }

    public function educations(): BelongsToMany
    {
        return $this->belongsToMany(Education::class, 'student_educations')
            ->withPivot(['level', 'type'])
            ->withTimestamps();
    }

    public function street(): BelongsTo
    {
        return $this->belongsTo(Street::class);
    }

    public function studentEducations(): HasMany
    {
        return $this->hasMany(StudentEducation::class, 'student_id');
    }

    public function getQrCodePath(): string
    {
        return $this->qr_code ?? '';
    }

    public function getFilamentName(): string
    {
        return "{$this->firstname} {$this->father_name} {$this->lastname}";
    }

    public function canHaveEntry(): bool
    {
        return $this->completed_data;
    }

    public function getEntryValidationErrors(): array
    {
        $errors = [];

        if (empty($this->firstname) || empty($this->father_name) || empty($this->lastname)) {
            $errors[] = 'الاسم الثلاثي غير مكتمل (الاسم الأول، اسم الأب، العائلة)';
        }

        if (empty($this->religion) || $this->religion === 0) {
            $errors[] = 'الديانة غير محددة';
        }

        if ($this->nationality === 'syrian' && empty($this->is_child)) {
            if (empty($this->national_number) && !$this->skip_national_number) {
                $errors[] = 'الرقم الوطني مطلوب للطلاب السوريين غير القاصرين';
            } elseif (!empty($this->national_number) && !preg_match('/^\d{11}$/', $this->national_number)) {
                $errors[] = 'الرقم الوطني يجب أن يحتوي على 11 رقمًا للطلاب السوريين';
            }
        }

        if ($this->nationality === 'syrian') {
            if (empty($this->phone)) {
                $errors[] = 'رقم الهاتف مطلوب للطلاب السوريين';
            } elseif (!preg_match('/^9\d{9}$/', $this->phone)) {
                $errors[] = 'رقم الهاتف السوري يجب أن يبدأ بالرقم 9 ويحتوي على 10 أرقام';
            }

            if (!empty($this->whatsapp) && !preg_match('/^9\d{9}$/', $this->whatsapp)) {
                $errors[] = 'رقم الواتساب السوري يجب أن يبدأ بالرقم 9 ويحتوي على 10 أرقام';
            }
        }

        if ($this->nationality === 'other' && !empty($this->whatsapp)) {
            if (!preg_match('/^[0-9+\-\s()]+$/', $this->whatsapp)) {
                $errors[] = 'رقم الواتساب الأجنبي غير صحيح';
            }
        }

        if ($this->nationality === 'other' && empty($this->national_number) && empty($this->is_child) && !$this->skip_national_number) {
            $errors[] = 'رقم الإقامة/الهوية الأجنبية مطلوب للطلاب غير السوريين غير القاصرين';
        }

        if (empty($this->street_id)) {
            $errors[] = 'الشارع غير محدد';
        }

        return $errors;
    }

    public function getValidationSummary(): string
    {
        $errors = $this->getEntryValidationErrors();
        if (empty($errors)) {
            return 'جميع البيانات مكتملة وصالحة للإدخال';
        }

        return implode("\n", $errors);
    }

    public function isDataComplete(): bool
    {
        return $this->completed_data;
    }

    public function updateCompletedData(): void
    {
        $this->completed_data = empty($this->getEntryValidationErrors());
        $this->saveQuietly();
    }


public function getEducationAtDate($date): ?StudentEducation
{
    return $this->studentEducations()
        ->where(function ($query) use ($date) {
            $query->where('start_date', '<=', $date)
                ->where(function ($q) use ($date) {
                    $q->where('end_date', '>=', $date)
                      ->orWhereNull('end_date');
                });
        })
        ->first();
}


   public function getEducationStatsForDateRange($startDate, $endDate)
{
    $startDate = $startDate instanceof \Carbon\Carbon ? $startDate : \Carbon\Carbon::parse($startDate);
    $endDate = $endDate instanceof \Carbon\Carbon ? $endDate : \Carbon\Carbon::parse($endDate);
    
    return $this->studentEducations()
        ->with('education')
        ->get()
        ->map(function ($studentEdu) use ($startDate, $endDate) {
            $eduStart = $studentEdu->start_date instanceof \Carbon\Carbon 
                ? $studentEdu->start_date 
                : \Carbon\Carbon::parse($studentEdu->start_date);
                
            $eduEnd = $studentEdu->end_date 
                ? ($studentEdu->end_date instanceof \Carbon\Carbon 
                    ? $studentEdu->end_date 
                    : \Carbon\Carbon::parse($studentEdu->end_date))
                : null;
            
            $overlapStart = $eduStart->greaterThan($startDate) ? $eduStart : $startDate;
            $overlapEnd = $eduEnd && $eduEnd->lessThan($endDate) ? $eduEnd : $endDate;
            
            $days = $overlapStart->lessThanOrEqualTo($overlapEnd) 
                ? $overlapStart->diffInDays($overlapEnd) + 1 
                : 0;
            
            return [
                'education' => $studentEdu->education,
                'student_education' => $studentEdu,
                'overlap_days' => $days,
                'overlap_start' => $overlapStart->toDateString(),
                'overlap_end' => $overlapEnd->toDateString(),
            ];
        })
        ->filter(fn($item) => $item['overlap_days'] > 0);
}

}
