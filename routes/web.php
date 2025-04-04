<?php


use Illuminate\Support\Facades\Route;
use App\Models\Student;
use SimpleSoftwareIO\QrCode\Facades\QrCode;


Route::get('/', function () {
    return view('welcome');
});

Route::get('/download-qr-code/{uuid}', function ($uuid) {
    $student = Student::findOrFail($uuid);
    $url = url("student/{$student->id}/entry");
    $qrCode = QrCode::size(200)->generate($url); // Generates as SVG by default

    return response($qrCode)
        ->header('Content-Type', 'image/svg+xml')
        ->header('Content-Disposition', 'attachment; filename="qr-code-' . $uuid . '.svg"');
})->name('download-qr-code');
