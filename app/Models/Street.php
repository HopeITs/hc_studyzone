<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Translatable\HasTranslations;

class Street extends Model
{
    use HasTranslations;
    protected $guarded = ['id'];
    protected $translatable = ['name'];

    public function city():BelongsTo{
        return $this->belongsTo(City::class);
    }
}
