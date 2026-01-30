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
    ];

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
        return empty($this->getEntryValidationErrors());
    }

    public function getEntryValidationErrors(): array
    {
        $errors = [];

        // 1. Check full name
        if (empty($this->firstname) || empty($this->father_name) || empty($this->lastname)) {
            $errors[] = 'الاسم الثلاثي غير مكتمل (الاسم الأول، اسم الأب، العائلة)';
        }

        // 2. Check religion
        if (empty($this->religion) || $this->religion === 0) {
            $errors[] = 'الديانة غير محددة';
        }

        // 3. Check national number for non-minor Syrians
        if ($this->nationality === 'syrian' && empty($this->is_child)) {
            if (empty($this->national_number)) {
                $errors[] = 'الرقم الوطني مطلوب للطلاب السوريين غير القاصرين';
            } elseif (!preg_match('/^\d{11}$/', $this->national_number)) {
                $errors[] = 'الرقم الوطني يجب أن يحتوي على 11 رقمًا للطلاب السوريين';
            }
        }

        // 4. Check phone number for Syrians
        if ($this->nationality === 'syrian') {
            if (empty($this->phone)) {
                $errors[] = 'رقم الهاتف مطلوب للطلاب السوريين';
            } elseif (!preg_match('/^9\d{9}$/', $this->phone)) {
                $errors[] = 'رقم الهاتف السوري يجب أن يبدأ بالرقم 9 ويحتوي على 10 أرقام';
            }

            // Check WhatsApp if provided (for Syrians)
            if (!empty($this->whatsapp) && !preg_match('/^9\d{9}$/', $this->whatsapp)) {
                $errors[] = 'رقم الواتساب السوري يجب أن يبدأ بالرقم 9 ويحتوي على 10 أرقام';
            }
        }

        // 5. For foreigners with WhatsApp, validate format (flexible)
        if ($this->nationality === 'other' && !empty($this->whatsapp)) {
            // For foreigners, WhatsApp can be any format but must be valid
            if (!preg_match('/^[0-9+\-\s()]+$/', $this->whatsapp)) {
                $errors[] = 'رقم الواتساب الأجنبي غير صحيح';
            }
        }

        // 6. Check foreign ID number for non-Syrians
        if ($this->nationality === 'other' && empty($this->national_number) && empty($this->is_child)) {
            $errors[] = 'رقم الإقامة/الهوية الأجنبية مطلوب للطلاب غير السوريين غير القاصرين';
        }

        // 7. Check street (address)
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
        return $this->canHaveEntry();
    }
}
