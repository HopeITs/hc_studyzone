<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\Roles;
use App\Models\Expense;
use App\Support\FinanceAccess;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * Streams an expense receipt from the private local disk after checking the
 * caller's center scope — the same role matrix as ExpenseResource::getEloquentQuery().
 */
class ExpenseReceiptController extends Controller
{
    public function __invoke(Expense $expense): Response
    {
        abort_unless(FinanceAccess::allowed(), 403);

        $user = Auth::user();

        $allowed = match (true) {
            $user->role === Roles::GeneralAdmin(),
            $user->role === Roles::Observer() => true,

            $user->role === Roles::CityAdmin() => $expense->center?->city_id === $user->city_id,

            $user->role === Roles::CenterAdmin(),
            $user->role === Roles::Cashier() => $expense->center_id === $user->center_id,

            default => false,
        };

        abort_unless($allowed, 403);

        abort_if(blank($expense->receipt_path), 404);

        $disk = Storage::disk('local');

        abort_unless($disk->exists($expense->receipt_path), 404);

        return $disk->response($expense->receipt_path);
    }
}
