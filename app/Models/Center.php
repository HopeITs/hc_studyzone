<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Traits\ActivityLog;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;


class Center extends Model
{
    use HasFactory  ,ActivityLog;



    protected $casts = [
    'name' => 'array',
];
    protected $guarded = ['id'];
    protected function name(): Attribute
    {
        return Attribute::make(
            get: function (mixed $value, array $attributes) {
                // Decode the JSON string from the database into an array
                $translations = json_decode($attributes['name'], true) ?: [];

                // Return the translation based on current locale,
                // with a fallback to English or the first available key.
                return $translations[app()->getLocale()]
                    ?? $translations['en']
                    ?? array_values($translations)[0]
                    ?? '';
            },
            // Optional: If you want to ensure it's saved as JSON
            set: fn (array $value) => json_encode($value),
        );
    }


    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    public function entries(): HasMany
    {
        return $this->hasMany(StudentEntry::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
