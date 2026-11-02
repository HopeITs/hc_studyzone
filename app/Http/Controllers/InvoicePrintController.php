<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\Roles;
use App\Models\Invoice;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;

class InvoicePrintController extends Controller
{
    public function __invoke(Invoice $invoice): View
    {
        $user = Auth::user();

        // Same role matrix as ShiftReportPrintController: CityAdmin only
        // within their own city, center-bound roles only their own center.
        $allowed = match (true) {
            $user->role === Roles::GeneralAdmin(),
            $user->role === Roles::Observer() => true,

            $user->role === Roles::CityAdmin() => $invoice->center?->city_id === $user->city_id,

            $user->role === Roles::CenterAdmin(),
            $user->role === Roles::Cashier() => $invoice->center_id === $user->center_id,

            default => false,
        };

        abort_unless($allowed, 403);

        $invoice->load(['items.inventoryItem', 'items.service', 'center', 'user']);

        return view('pos.invoice-print', ['invoice' => $invoice]);
    }
}
