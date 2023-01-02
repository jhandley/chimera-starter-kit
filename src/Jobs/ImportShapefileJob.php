<?php

namespace Uneca\Chimera\Jobs;

use Illuminate\Support\Str;
use Uneca\Chimera\Models\Area;
use Uneca\Chimera\Notifications\TaskCompletedNotification;
use Uneca\Chimera\Notifications\TaskFailedNotification;
use Uneca\Chimera\Services\ShapefileImporter;
use Uneca\Chimera\Traits\Geospatial;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Auth\User;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ImportShapefileJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    use Geospatial;

    public $timeout = 1200;

    public function __construct(private string $filePath, private int $level, private User $user)
    {
    }

    private function augumentData(array $features, int $level)
    {
        return array_map(function ($feature) use ($level) {
            if ($level > 0) {
                $ancestor = self::findContainingGeometry($level - 1, $feature['geom']);
                $feature['path'] = empty($ancestor) ? null : $this->makePath($ancestor, $feature['attribs']['code']);
            } else {
                $feature['path'] = $this->makePath(null, $feature['attribs']['code']);
            }
            return $feature;
        }, $features);
    }

    private function makePath($ancestor, $code)
    {
        return is_null($ancestor) ? $code : $ancestor->path . '.' . $code;
    }

    private function validateShapefile(array $features)
    {
        // Check for empty shapefiles?
        if (empty($features)) {
            throw ValidationException::withMessages([
                'shapefile' => ['The shapefile does not contain any valid features.'],
            ]);
        }

        // Check that shapefile has 'name' and 'code' columns in the attribute table
        $firstFeatureAttributes = $features[0]['attribs'];
        if (! (array_key_exists('name', $firstFeatureAttributes) && array_key_exists('code', $firstFeatureAttributes))) {
            throw ValidationException::withMessages([
                'shapefile' => ["The shapefile needs to have 'name' and 'code' among its attributes"],
            ]);
        }
    }

    public function handle()
    {
        $importer = new ShapefileImporter();
        $features = $importer->import($this->filePath);
        $this->validateShapefile($features);
        // Check that all areas have valid value for 'code' and unique within the level
        $featuresWithInvalidCode = array_filter($features, function ($feature) {
            $codeValidator = Validator::make(
                $feature['attribs'],
                ['code' => [
                    'required', 'max:255', 'regex:/[A-Za-z0-9_]+/i',
                    // Rule::unique('areas', 'code')->where(fn ($query) => $query->where('level', $this->level))
                ]]
            );
            if ($codeValidator->fails()) {
                logger('Shapefile validation error', ['Error' => $codeValidator->errors()->all()]);
            }
            return $codeValidator->fails();
        });
        if (! empty($featuresWithInvalidCode)) {
            throw ValidationException::withMessages([
                'shapefile' => [count($featuresWithInvalidCode) . " area(s) with invalid value for 'code' attribute found."],
            ]);
        }

        // Find and add parent info for all areas (unless root level)
        $augmentedFeatures = $this->augumentData($features, $this->level);

        // Check that there are no "orphan" areas
        $orphanFeatures = array_filter($augmentedFeatures, fn ($feature) => empty($feature['path']));
        if (! config('chimera.area.map.ignore_orphan_areas') && ! empty($orphanFeatures)) {
            $orphans = collect($orphanFeatures)->pluck('attribs.code')->join(', ', ' and ');
            throw ValidationException::withMessages([
                'shapefile' => [count($orphanFeatures) . " orphan area(s) found [code: $orphans]. All areas require a containing parent area."],
            ]);
        }

        DB::transaction(function() use ($augmentedFeatures) {
            $results = [];
            foreach ($augmentedFeatures as $feature) {
                $name = Str::of($feature['attribs']['name'])->trim()->lower()->limit(80)->title();
                $results[] = Area::create([
                    'name' => $name,
                    'code' => $feature['attribs']['code'],
                    'level' => $this->level,
                    'geom' => $feature['geom'],
                    'path' => $feature['path'],
                ]);
            }
            $insertedCount = collect($results)->filter()->count();

            Notification::sendNow($this->user, new TaskCompletedNotification(
                'Task completed',
                "$insertedCount areas have been imported."
            ));
        });
    }

    public function failed(\Throwable $exception)
    {
        logger('ImportShapefile Job Failed', ['Exception: ' => $exception->getMessage()]);
        Notification::sendNow($this->user, new TaskFailedNotification(
            'Task failed',
            $exception->getMessage()
        ));
    }
}
