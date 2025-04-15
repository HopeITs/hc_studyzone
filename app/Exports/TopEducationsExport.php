<?php

namespace App\Exports;

use App\Models\Education;
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;

class TopEducationsExport implements FromView
{
    protected $data;

    public function __construct($educations)
    {
        $this->data = $educations;
    }

    public function view(): View
    {
        return view('exports.top-educations', [
            'educations' => $this->data,
        ]);
    }
}
