<?php

namespace App\Http\Controllers;

use App\Http\Requests\SizeMappingExclusionStore;
use App\SizeMappingExclusion;

class SizeMappingExclusionController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $sizeMappingExclusions = SizeMappingExclusion::query()->paginate();

        return view('size-mapping-exclusion.index', ['sizeMappingExclusions' => $sizeMappingExclusions]);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  SizeMappingExclusionStore  $request
     * @return \Illuminate\Http\Response
     */
    public function store(SizeMappingExclusionStore $request)
    {
        $sizeMappingExclusion = new SizeMappingExclusion();
        $sizeMappingExclusion->article_number = $request->input('article_number');
        $sizeMappingExclusion->save();

        return back();
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\SizeMappingExclusion $sizeMappingExclusion
     * @return \Illuminate\Http\Response
     * @throws \Exception
     */
    public function destroy(SizeMappingExclusion $sizeMappingExclusion)
    {
        $sizeMappingExclusion->delete();

        return redirect()->route('size-mapping-exclusions.index');
    }
}
