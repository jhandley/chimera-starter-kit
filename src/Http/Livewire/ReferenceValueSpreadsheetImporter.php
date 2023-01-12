<?php

namespace Uneca\Chimera\Http\Livewire;

use Uneca\Chimera\Jobs\ImportReferenceValueSpreadsheetJob;
use Uneca\Chimera\Services\AreaTree;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\WithFileUploads;
use Spatie\SimpleExcel\SimpleExcelReader;

class ReferenceValueSpreadsheetImporter extends Component
{
    use WithFileUploads;

    public $spreadsheet;
    public bool $fileAccepted = false;
    public int $indicatorsToImport = 1;
    public array $columnHeaders = [];
    public array $columnMapping = [];
    public $filePath = '';
    public string $message = '';
    public array $levels;

    protected function rules()
    {
        $columnMappingRules = collect(Arr::dot(
            Collection::times($this->indicatorsToImport, function ($number) {
                return [
                    'name' => 'required',
                    'path' => 'required',
                    //'code' => 'required',
                ];
            })->all()
        ))->mapWithKeys(fn ($v, $k) => ["columnMapping.{$k}" => $v]);
        return array_merge(['spreadsheet' => 'required|file|mimes:csv'], $columnMappingRules->all());
    }

    protected function messages()
    {
        return collect(Arr::dot(
            Collection::times($this->indicatorsToImport, function ($number) {
                return [
                    'name' => 'required',
                    'path' => 'required',
                    //'code' => 'required',
                ];
            })->all()
        ))->mapWithKeys(fn ($v, $k) => ["columnMapping.{$k}" => $v])->all();
    }

    public function mount()
    {
        $this->levels = (new AreaTree)->hierarchies;
        $this->columnMapping = Collection::times($this->indicatorsToImport, function () {
            return ['name' => '', 'path' => '', 'level' => array_key_last($this->levels), 'isAdditive' => true]; // 'code' => '', 'zeroPadding' => 0,
        })->all();
    }

    public function updatedSpreadsheet()
    {
        $this->validateOnly('spreadsheet');
        $filename = collect([Str::random(40), $this->spreadsheet->getClientOriginalExtension()])->join('.');
        $this->spreadsheet->storeAs('/spreadsheets', $filename, 'imports');
        $this->filePath = Storage::disk('imports')->path('spreadsheets/' . $filename);
        $this->columnHeaders = SimpleExcelReader::create($this->filePath)->getHeaders();
        $this->fileAccepted = true;
    }

    public function add()
    {
        $this->indicatorsToImport++;
        $this->columnMapping[] = ['name' => '', 'path' => '', 'level' => array_key_last($this->levels), 'isAdditive' => true]; // 'code' => '', 'zeroPadding' => 0,
    }

    public function import()
    {
        $this->validate();
        ImportReferenceValueSpreadsheetJob::dispatch($this->filePath, $this->columnMapping, auth()->user());
        $this->message = "The file is being imported. You will receive a notification when the process is complete.";
    }

    public function render()
    {
        return view('chimera::livewire.reference-value-spreadsheet-importer');
    }
}
