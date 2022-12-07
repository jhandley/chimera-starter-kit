<?php

namespace Uneca\Chimera;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Uneca\Chimera\Commands\Adminify;
use Uneca\Chimera\Commands\Chimera;
use Uneca\Chimera\Commands\DataExport;
use Uneca\Chimera\Commands\DataImport;
use Uneca\Chimera\Commands\Delete;
use Uneca\Chimera\Commands\Dockerize;
use Uneca\Chimera\Commands\DownloadIndicatorTemplates;
use Uneca\Chimera\Commands\GenerateReports;
use Uneca\Chimera\Commands\MakeIndicator;
use Uneca\Chimera\Commands\MakeMapIndicator;
use Uneca\Chimera\Commands\MakeReport;
use Uneca\Chimera\Commands\MakeScorecard;

class ChimeraStarterKitServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $migrations = [
            'install_postgis_extension',
            'install_ltree_extension',
            'create_area_restrictions_table',
            'create_faqs_table',
            'create_invitations_table',
            'create_usage_stats_table',
            'create_areas_table',
            'create_pages_table',
            'create_questionnaires_table',
            'create_indicators_table',
            'create_indicator_page_table',
            'create_scorecards_table',
            'create_reports_table',
            'add_is_suspended_column_to_users_table',
            'create_notifications_table',
            'create_announcements_table',
            'create_reference_values_table',
            'create_area_hierarchies_table',
            'create_map_indicators_table',
        ];
        $package
            ->name('chimera')
            ->hasViews()
            //->hasViewComponents('chimera',ChartCard::class, SimpleCard::class)
            ->hasConfigFile(['chimera', 'languages', 'filesystems'])
            //->hasTranslations() // Makes translations publishable
            //->hasRoute('web')
            ->hasMigrations($migrations)
            ->hasCommands([
                Chimera::class,
                DataExport::class,
                DataImport::class,
                Dockerize::class,
                Adminify::class,
                Delete::class,
                DownloadIndicatorTemplates::class,
                GenerateReports::class,
                MakeIndicator::class,
                MakeMapIndicator::class,
                MakeReport::class,
                MakeScorecard::class,
            ]);
        ;
    }
}
