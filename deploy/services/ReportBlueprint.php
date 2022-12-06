<?php

namespace App\Services;

use App\Models\Report;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Spatie\SimpleExcel\SimpleExcelWriter;

abstract class ReportBlueprint
{
    public string $questionnaire;
    public $report;
    public $file;
    public $fileType = 'csv';

    public function __construct(Report $report, ?string $questionnaire = null)
    {
        $this->questionnaire = $questionnaire;
        $this->report = $report;
        $this->file = "{$report->slug}.{$this->fileType}";
    }

    abstract public function getData(): Collection;

    public function writeFile($data)
    {
        $writer = SimpleExcelWriter::create(Storage::disk('reports')->path($this->file))->addRows($data);
    }

    public function download()
    {
        return Storage::disk('reports')->download($this->file);
    }

    public function generate()
    {
        $data = $this->getData();
        if (empty($data)) {
            throw new Exception('There is no data to export');
        }
        $rowified = $data->map(function ($obj) {
            return (array)$obj;
        })->all();
        $this->writeFile($rowified);

        $this->report->update(['last_generated_at' => now()]);
    }
}
