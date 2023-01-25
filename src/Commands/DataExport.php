<?php

namespace Uneca\Chimera\Commands;

use Illuminate\Console\Command;
use Uneca\Chimera\Models\Questionnaire;

class DataExport extends Command
{
    protected $signature = 'chimera:data-export';

    protected $description = 'Dump postgres data (from some tables) to file';

    protected array $tables = [
        'area_hierarchies',
        //'areas',
        //'reference_values',
        'questionnaires',
        'pages',
        'indicators',
        'indicator_page',
        'scorecards',
        'reports',
        'map_indicators',
        //'roles',
        'permissions',
        //'role_has_permissions'
    ];

    public function handle()
    {
        $pgsqlConfig = config('database.connections.pgsql');
        $tmpFile = base_path() . '/data-export.tmp';
        $dumpFile = base_path() . '/data-export.sql';

        \Spatie\DbDumper\Databases\PostgreSql::create()
            ->setDbName($pgsqlConfig['database'])
            ->setUserName($pgsqlConfig['username'])
            ->setPassword($pgsqlConfig['password'])
            ->includeTables($this->tables)
            ->doNotCreateTables()
            ->addExtraOption('--inserts')
            ->addExtraOption('--on-conflict-do-nothing')
            ->dumpToFile($tmpFile);

        if (! file_exists($dumpFile)) {
            $tmpFileHandle = fopen($tmpFile, 'r');
            $dumpFileHandle = fopen($dumpFile, 'w');
            $databasePasswords = Questionnaire::pluck('password')->all();
            while (($line = fgets($tmpFileHandle)) !== false) {
                if (! empty(trim($line))) {
                    if (str_contains($line, 'INSERT INTO public.questionnaires')) {
                        $line = str_replace($databasePasswords, '*****', $line);
                    }
                    fwrite($dumpFile, $line);
                }
            }
            fclose($dumpFileHandle);
            fclose($tmpFileHandle);
            unlink($tmpFile);

            $this->newLine()->info('The postgres data has been dumped to file');
            $this->newLine();
            return Command::SUCCESS;
        }
        $this->newLine()->error('There was a problem dumping the postgres database');
        $this->newLine();
        return Command::FAILURE;
    }
}
