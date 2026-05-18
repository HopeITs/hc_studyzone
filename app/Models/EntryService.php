<?php

namespace App\Models;


use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EntryService extends Model
{


    protected $guarded = ['id'];



    public function entry(): BelongsTo
    {
        return $this->belongsTo(StudentEntry::class, 'entry_id');
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }
}
