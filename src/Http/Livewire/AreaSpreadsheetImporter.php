<?php

namespace Uneca\Chimera\Http\Livewire;

use Uneca\Chimera\Jobs\ImportAreaSpreadsheetJob;
use Uneca\Chimera\Services\AreaTree;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\WithFileUploads;
use Spatie\SimpleExcel\SimpleExcelReader;

class AreaSpreadsheetImporter extends Component
{
    use WithFileUploads;

    public $spreadsheet;
    public bool $fileAccepted = false;
    public array $areaLevels = [];
    public array $columnHeaders = [];
    public array $columnMapping = [];
    public $filePath = '';
    public string $message = '';

    protected function rules()
    {
        $columnMappingRules = Arr::dot(
            collect($this->areaLevels)
                ->map(fn ($level) => "columnMapping.{$level}")
                ->mapWithKeys(function ($level) {
                    return [$level => [
                        'name' => 'required',
                        'code' => 'required',
                        'zeroPadding' => 'numeric|min:0'
                    ]];
                })
                ->all()
        );
        return array_merge(['spreadsheet' => 'required|file|mimes:csv,xlsx'], $columnMappingRules);
    }

    protected function messages()
    {
        return Arr::dot(
            collect($this->areaLevels)
                ->map(fn ($level) => "columnMapping.{$level}")
                ->mapWithKeys(function ($level) {
                    return [$level => [
                        'name' => 'required',
                        'code' => 'required',
                        'zeroPadding' => 'invalid'
                    ]];
                })
                ->all()
        );
    }

    public function mount()
    {
        $this->areaLevels = (new AreaTree())->hierarchies;
        $this->columnMapping = collect($this->areaLevels)->mapWithKeys(function ($levelName) {
            return [$levelName => ['name' => '', 'code' => '', 'zeroPadding' => 0]];
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

    public function import()
    {
        $this->validate();
        ImportAreaSpreadsheetJob::dispatch($this->filePath, $this->areaLevels, $this->columnMapping, auth()->user());
        $this->message = "The file is being imported. You will receive a notification when the process is complete.";
    }

    public function render()
    {
        return view('chimera::livewire.area-spreadsheet-importer');
    }
}