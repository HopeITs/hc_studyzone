<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;

class ArrayExport implements FromArray
{
    protected $data;
    protected $headers;

    public function __construct(array $data, array $headers)
    {
        $this->data = $data;
        $this->headers = $headers;
    }

    public function array(): array
    {
        return $this->data;
    }

    public function headings(): array
    {
        return $this->headers;
    }
}
