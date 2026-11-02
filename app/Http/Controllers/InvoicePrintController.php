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

        $allowed = match (true) {
            $user->role === Roles::GeneralAdmin(),
            $user->role === Roles::CityAdmin() => true,

            $user->role === Roles::CenterAdmin() => $invoice->center_id === $user->center_id,

            default => false,
        };

        abort_unless($allowed, 403);

        $invoice->load(['items.inventoryItem', 'items.service', 'center', 'user']);

        return view('pos.invoice-print', ['invoice' => $invoice]);
    }
}
