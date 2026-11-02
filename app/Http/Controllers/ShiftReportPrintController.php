<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\CashShiftStatus;
use App\Enums\InvoiceStatus;
use App\Enums\Roles;
use App\Models\CashShift;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;

class ShiftReportPrintController extends Controller
{
    public function __invoke(CashShift $shift): View
    {
        $user = Auth::user();

        $shift->load(['center', 'user']);

        $allowed = match (true) {
            $user->role === Roles::GeneralAdmin(),
            $user->role === Roles::Observer() => true,

            $user->role === Roles::CityAdmin() => $shift->center?->city_id === $user->city_id,

            $user->role === Roles::CenterAdmin() => $shift->center_id === $user->center_id,

            default => false,
        };

        abort_unless($allowed, 403);

        // Only closed shifts have a final snapshot worth printing.
        abort_if($shift->status !== CashShiftStatus::Closed, 404);

        $invoicesCount = $shift->invoices()
            ->where('status', InvoiceStatus::Paid->value)
            ->count();

        return view('cash-shifts.close-report', [
            'shift' => $shift,
            'invoicesCount' => $invoicesCount,
        ]);
    }
}
