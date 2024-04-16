<?php

namespace Uneca\Chimera\Commands;

use Uneca\Chimera\Models\MapIndicator;
use Uneca\Chimera\Models\DataSource;
use Illuminate\Console\GeneratorCommand;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use function Laravel\Prompts\select;
use function Laravel\Prompts\error;
use function Laravel\Prompts\text;
use function Laravel\Prompts\info;
use function Laravel\Prompts\textarea;

class MakeMapIndicator extends GeneratorCommand
{
    protected $signature = 'chimera:make-map-indicator';

    protected $description = 'Create a new map indicator. Creates file from stub and adds entry in map_indicators table.';

    protected $type = 'default';

    protected function getDefaultNamespace($rootNamespace)
    {
        return $rootNamespace . '\MapIndicators';
    }

    protected function getStub()
    {
        return resource_path("stubs/map_indicators/{$this->type}.stub");
    }

    protected function writeFile(string $name)
    {
        $className = $this->qualifyClass($name);
        $path = $this->getPath($className);
        $this->makeDirectory($path);
        $content = $this->buildClass($className);
        return $this->files->put($path, $content);
    }

    private function ensureMapIndicatorsPermissionExists()
    {
        Permission::firstOrCreate(['guard_name' => 'web', 'name' => 'map_indicators']);
    }

    public function handle()
    {
        if (DataSource::all()->isEmpty()) {
            error("You have not yet added data sources to your dashboard. Please do so first.");
            return self::FAILURE;
        }

        $name = text(
            label: "Map indicator name (this will be the component name and has to be in camel case)",
            placeholder: 'Household/BirthRate',
            hint: "Eg. HouseholdsEnumeratedByDay or Household/BirthRate (including directory helps organize indicator files)",
            validate: ['required', 'string', 'regex:/^[A-Z][A-Za-z\/]*$/', 'unique:map_indicators,name']
        );

        $dataSources = DataSource::pluck('name')->toArray();
        $questionnaireMenu = array_combine(range(1, count($dataSources)), array_values($questionnaires));

        $questionnaire = select("Which questionnaire does this map indicator belong to?", $questionnaireMenu);
        $title = text(
            label: "Please enter a reader friendly title for the map indicator (press enter to leave empty for now) ",
            validate: ['nullable',]
        );
        $description = textarea(
            label: "Please enter a description for the map indicator (press enter to leave empty for now)",
            validate: ['nullable',]
        );
        $this->ensureMapIndicatorsPermissionExists();
        DB::transaction(function () use ($name, $title, $description, $questionnaire) {
            $result = $this->writeFile($name);
            if ($result) {
                info('Map indicator created successfully.');
            } else {
                throw new \Exception('There was a problem creating the map indicator file');
            }
            MapIndicator::create([
                'name' => $name,
                'title' => $title,
                'description' => $description,
                'questionnaire' => $questionnaire,
            ]);
        });

        return self::SUCCESS;
    }
}
