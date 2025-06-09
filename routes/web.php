<?php


use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;
use App\Models\Student;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use App\Http\Controllers\StudentEntryController;
use App\Filament\Widgets\EntryGenderChart;
use App\Filament\Widgets\NewStudentsChart;
use App\Filament\Widgets\AllEntriesChart;
use App\Filament\Widgets\EntryToolsChart;
use App\Filament\Widgets\HourlyAverageChart;
use App\Filament\Widgets\StudentGenderChart;
use App\Filament\Widgets\FrequentStudentsChart;
use App\Filament\Resources\StudentResource;
use App\Http\Controllers\SocialLinkController;

Route::get('/', function () {
    return view('welcome');
});
Route::get('/clear-cache', function () {
    Artisan::call('optimize:clear'); // instead of cache:clear
    session()->flash('success', 'Cache');
    return redirect()->route('filament.admin.pages.dashboard')->withSuccess('Cache has been cleared');
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


Route::post('/filament/widgets/all-entries/export', function () {
    $widget = new AllEntriesChart();
    return $widget->export();
})->name('filament.widgets.export-all-entries');


Route::post('/filament/widgets/new-students/export', function () {
    $widget = new NewStudentsChart();
    return $widget->export();
})->name('filament.widgets.export-new-students');


Route::post('/filament/widgets/entry-tools/export', function () {
    $widget = new EntryToolsChart();
    return $widget->export();
})->name('filament.widgets.export-entry-tools');


Route::post('/filament/widgets/hourly-average/export', function () {
    $widget = new HourlyAverageChart();
    return $widget->export();
})->name('filament.widgets.export-hourly-average');


Route::post('/filament/widgets/student-gender/export', function () {
    $widget = new StudentGenderChart();
    return $widget->export();
})->name('filament.widgets.export-student-gender');


Route::post('/filament/widgets/frequent-students/export', function () {
    $widget = new FrequentStudentsChart();
    return $widget->export();
})->name('filament.widgets.export-frequent-students');


Route::get('/admin/students/scan', function () {
    return view('filament.qr-code-scanner');
})->name('filament.admin.resources.students.scan')->middleware('auth');


Route::get('/qr/studyzone/{city}', [SocialLinkController::class, 'show'])
     ->where('city', 'homs|damascus|aleppo|latakia|mouzeina|tartus')
     ->name('info.show');

Route::get('/test-419', function () {
    abort(419);
});
