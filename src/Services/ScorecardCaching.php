<?php

namespace Uneca\Chimera\Services;

use Uneca\Chimera\Models\Indicator;
use Uneca\Chimera\Models\MapIndicator;
use Uneca\Chimera\Models\DataSource;
use Uneca\Chimera\Models\Scorecard;

class ScorecardCaching extends Caching
{
    public function __construct(Scorecard|MapIndicator|Indicator|DataSource $model, array $filter)
    {
        $this->model = $model;
        $this->instance = DashboardComponentFactory::makeScorecard($model);
        $this->filter = $filter;
        $this->key = $this->model->slug . implode('-', array_filter($filter));
    }

    public function tags(): array
    {
        return ['scorecards', $this->model->dataSource];
    }
}
