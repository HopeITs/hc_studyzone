<?php

use App\Exports\ActiveDaysPerCenterExport;
use App\Exports\EntriesByCenterDailyExport;
use App\Exports\EntriesByCenterExport;
use App\Filament\Widgets\AllEntriesChart;
use App\Filament\Widgets\EntryGenderChart;
use App\Filament\Widgets\EntryToolsChart;
use App\Filament\Widgets\FrequentStudentsChart;
use App\Filament\Widgets\HourlyAverageChart;
use App\Filament\Widgets\NewStudentsChart;
use App\Filament\Widgets\StudentGenderChart;
use App\Http\Controllers\ExpenseReceiptController;
use App\Http\Controllers\InvoicePrintController;
use App\Http\Controllers\ShiftReportPrintController;
use App\Http\Controllers\SocialLinkController;
use App\Http\Controllers\StudentEntryController;
use App\Http\Middleware\EnsureUserIsActive;
use App\Models\Student;
use Carbon\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;
use Maatwebsite\Excel\Facades\Excel;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

Route::get('/clear-cache', function () {
    Artisan::call('optimize:clear'); // instead of cache:clear
    session()->flash('success', 'Cache');

})->middleware(['auth', 'admin.access']);

// Financial documents: besides auth, a deactivated account must not keep
// printing invoices/reports or downloading receipts on a stale session.
Route::group(['middleware' => ['auth', EnsureUserIsActive::class]], function () {
    Route::get('/invoices/{invoice}/print', InvoicePrintController::class)
        ->name('pos.invoice.print');

    Route::get('/cash-shifts/{shift}/print', ShiftReportPrintController::class)
        ->name('cash-shifts.print');

    Route::get('/expenses/{expense}/receipt', ExpenseReceiptController::class)
        ->name('expenses.receipt');
});

Route::group(['middleware' => ['auth']], function () {
    Route::get('/download-qr-code/{uuid}', function ($uuid) {
        $student = Student::findOrFail($uuid);
        $url = url("student/{$student->id}/entry");
        $qrCode = QrCode::size(200)->generate($url);

        return response($qrCode)
            ->header('Content-Type', 'image/svg+xml')
            ->header('Content-Disposition', 'attachment; filename="qr-code-'.$uuid.'.svg"');
    })->name('download-qr-code');

    Route::get('/test-export/{from}/{to}', function ($from, $to) {

        $startDate = Carbon::parse($from)->startOfDay();
        $endDate = Carbon::parse($to)->endOfDay();

        return Excel::download(
            new EntriesByCenterExport($startDate, $endDate),
            'entries-per-month.xlsx'
        );
    })->name('test-export');

    Route::get('/entries-by-center-daily-export/{from}/{to}', function ($from, $to) {

        $startDate = Carbon::parse($from)->startOfDay();
        $endDate = Carbon::parse($to)->endOfDay();

        return Excel::download(
            new EntriesByCenterDailyExport($startDate, $endDate),
            'entries-per-day.xlsx'
        );
    })->name('entries-by-center-daily-export');

    Route::get('/active-days-export/{from}/{to}', function ($from, $to) {

        $startDate = Carbon::parse($from)->startOfDay();
        $endDate = Carbon::parse($to)->endOfDay();

        return Excel::download(
            new ActiveDaysPerCenterExport($startDate, $endDate),
            'active-days-per-month.xlsx'
        );
    })->name('active-days-export');

    Route::get('/export-entries', function () {
        return Excel::download(new EntriesByCenterExport(now()->startOfYear(), now()->endOfMonth()), 'entries_by_center.xlsx');
    });

    // Route::get('/student/{uuid}/entry', [StudentEntryController::class, 'showEntryForm'])->name('student.entry');
    Route::post('/student/{uuid}/entry', [StudentEntryController::class, 'store'])->name('student.entry.store');

    Route::post('/filament/widgets/entry-gender/export', function () {
        $widget = new EntryGenderChart;

        return $widget->export();
    })->name('filament.widgets.export-entry-gender');

    Route::post('/filament/widgets/all-entries/export', function () {
        $widget = new AllEntriesChart;

        return $widget->export();
    })->name('filament.widgets.export-all-entries');

    Route::post('/filament/widgets/new-students/export', function () {
        $widget = new NewStudentsChart;

        return $widget->export();
    })->name('filament.widgets.export-new-students');

    Route::post('/filament/widgets/entry-tools/export', function () {
        $widget = new EntryToolsChart;

        return $widget->export();
    })->name('filament.widgets.export-entry-tools');

    Route::post('/filament/widgets/hourly-average/export', function () {
        $widget = new HourlyAverageChart;

        return $widget->export();
    })->name('filament.widgets.export-hourly-average');

    Route::post('/filament/widgets/student-gender/export', function () {
        $widget = new StudentGenderChart;

        return $widget->export();
    })->name('filament.widgets.export-student-gender');

    Route::post('/filament/widgets/frequent-students/export', function () {
        $widget = new FrequentStudentsChart;

        return $widget->export();
    })->name('filament.widgets.export-frequent-students');

    Route::get('/students/scan', function () {
        return view('filament.qr-code-scanner');
    })->name('filament.resources.students.scan')->middleware('auth');
});

Route::get('/qr/{city}', [SocialLinkController::class, 'show'])
    ->where('city', 'homs|damascus|aleppo|latakia|tartus')
    ->name('info.show');

Route::get('/test-419', function () {
    abort(419);
});
