<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ExpenseType;
use App\Traits\ActivityLog;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Expense extends Model
{
    use ActivityLog, HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'type' => ExpenseType::class,
        'amount' => 'integer',
        'date' => 'date',
    ];

    public function center(): BelongsTo
    {
        return $this->belongsTo(Center::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(CashShift::class, 'shift_id');
    }
}
