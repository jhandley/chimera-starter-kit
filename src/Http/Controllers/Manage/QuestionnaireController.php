<?php

namespace Uneca\Chimera\Http\Controllers\Manage;

use App\Http\Controllers\Controller;
use Livewire\Features\SupportConsoleCommands\Commands\ComponentParser;
use Livewire\Mechanisms\ComponentRegistry;
use ReflectionClass;
use Uneca\Chimera\Http\Requests\QuestionnaireRequest;
use Uneca\Chimera\Models\Questionnaire;
use Illuminate\Support\Facades\Storage;

class QuestionnaireController extends Controller
{
    private array $databases = [
        'MySQL 5.7+/MariaDB 10.3+' => 'mysql',
        'PostgreSQL 10.0+' => 'pgsql',
        'SQLite 3.8.8+' => 'sqlite',
        'SQL Server 2017+' => 'sqlsrv',
    ];

    public function index()
    {
        $records = Questionnaire::orderBy('rank')->get();
        return view('chimera::developer.questionnaire.index', compact('records'));
    }

    private function getCaseStatComponentsList()
    {
        $filesystem = Storage::build([
            'driver' => 'local',
            'root' => base_path(),
        ]);
        return collect($filesystem->allFiles('app/Livewire'))
            ->filter(function($file) {
                return str($file)->contains('CaseStats');
            })
            ->mapWithKeys(function($file) {
                $componentName = str($file)->after('app/Livewire/')->before('.php')->kebab()->__toString();
                $qualifiedName = str((new ComponentRegistry)->getClass($componentName))->ltrim("\\");
                return [$componentName => $qualifiedName];
            })
            ->merge(['case-stats' => 'Uneca\Chimera\Http\Livewire\CaseStats (default)'])
            ->reverse();
    }

    public function create()
    {
        $components = $this->getCaseStatComponentsList();
        return view('chimera::developer.questionnaire.create', compact('components'))
            ->with(['databases' => $this->databases]);
    }

    public function store(QuestionnaireRequest $request)
    {
        Questionnaire::create($request->only([
            'name', 'title', 'start_date', 'end_date', 'show_on_home_page', 'rank', 'host', 'port', 'database',
            'username', 'password', 'connection_active', 'case_stats_component', 'driver'
        ]));
        return redirect()->route('developer.questionnaire.index')->withMessage('Record created');
    }

    public function edit(Questionnaire $questionnaire)
    {
        $components = $this->getCaseStatComponentsList();
        return view('chimera::developer.questionnaire.edit', compact('questionnaire', 'components'))
            ->with(['databases' => $this->databases]);
    }

    public function update(Questionnaire $questionnaire, QuestionnaireRequest $request)
    {
        $questionnaire->update($request->only([
            'name', 'title', 'start_date', 'end_date', 'show_on_home_page', 'rank', 'host', 'port', 'database',
            'username', 'password', 'connection_active', 'case_stats_component', 'driver'
        ]));
        return redirect()->route('developer.questionnaire.index')->withMessage('Record updated');
    }

    public function destroy(Questionnaire $questionnaire)
    {
        $questionnaire->delete();
        return redirect()->route('developer.questionnaire.index')->withMessage('Record deleted');
    }

    public function test(Questionnaire $questionnaire)
    {
        $results = $questionnaire->test();
        $passesTest = $results->reduce(function ($carry, $item) {
            return $carry && $item['passes'];
        }, true);
        if ($passesTest) {
            return redirect()->route('developer.questionnaire.index')
                ->withMessage('Connection test successful');
        } else {
            return redirect()->route('questionnaire.index')
                ->withErrors($results->pluck('message')->filter()->all());
        }
    }
}
