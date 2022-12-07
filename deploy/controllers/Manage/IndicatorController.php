<?php

namespace App\Http\Controllers\Manage;

use App\Http\Controllers\Controller;
use App\Http\Requests\IndicatorRequest;
use App\Models\Indicator;
use App\Models\Page;

class IndicatorController extends Controller
{
    public function index()
    {
        $records = Indicator::with('pages')->orderBy('title')->get();
        return view('chimera::indicator.index', compact('records'));
    }

    public function edit(Indicator $indicator)
    {
        $pages = Page::pluck('title', 'id');
        return view('chimera::indicator.edit', compact('indicator', 'pages'));
    }

    public function update(Indicator $indicator, IndicatorRequest $request)
    {
        $indicator->pages()->sync($request->get('pages', []));
        $indicator->update($request->only(['title', 'description', 'help', 'published']));
        return redirect()->route('indicator.index')->withMessage('Record updated');
    }
}
