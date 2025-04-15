<?php


use Illuminate\Support\Facades\Route;
use App\Models\Student;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use App\Http\Controllers\StudentEntryController;
use App\Filament\Widgets\EntryGenderChart;




Route::get('/', function () {
    return view('welcome');
});

Route::get('/download-qr-code/{uuid}', function ($uuid) {
    $student = Student::findOrFail($uuid);
    $url = url("student/{$student->id}/entry");
    $qrCode = QrCode::size(200)->generate($url);

    return response($qrCode)
        ->header('Content-Type', 'image/svg+xml')
        ->header('Content-Disposition', 'attachment; filename="qr-code-' . $uuid . '.svg"');
})->name('download-qr-code');


Route::get('/student/{uuid}/entry', [StudentEntryController::class, 'showEntryForm'])->name('student.entry');
Route::post('/student/{uuid}/entry', [StudentEntryController::class, 'store'])->name('student.entry.store');



Route::post('/filament/widgets/entry-gender/export', function () {
    $widget = new EntryGenderChart();
    return $widget->export();
})->name('filament.widgets.export-entry-gender');
