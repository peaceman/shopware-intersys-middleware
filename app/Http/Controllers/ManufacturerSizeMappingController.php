<?php

namespace App\Http\Controllers;

use App\Http\Requests\ManufacturerSizeMappingStore;
use App\Http\Requests\ManufacturerSizeMappingUpdate;
use App\Manufacturer;
use App\ManufacturerSizeMapping;
use Illuminate\Http\Response;

class ManufacturerSizeMappingController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @param Manufacturer $manufacturer
     * @return \Illuminate\Http\Response
     */
    public function index(Manufacturer $manufacturer)
    {
        $sizeMappings = $manufacturer->sizeMappings;

        return response()->json(['data' => $sizeMappings]);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param ManufacturerSizeMappingStore $request
     * @param Manufacturer $manufacturer
     * @return void
     */
    public function store(ManufacturerSizeMappingStore $request, Manufacturer $manufacturer)
    {
        $sizeMapping = new ManufacturerSizeMapping();
        $sizeMapping->gender = $request->input('gender');
        $sizeMapping->source_size = $request->input('source_size');
        $sizeMapping->target_size = $request->input('target_size');

        $manufacturer->sizeMappings()->save($sizeMapping);

        return response()->json(['data' => $sizeMapping, Response::HTTP_CREATED]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param ManufacturerSizeMappingUpdate $request
     * @param Manufacturer $manufacturer
     * @param \App\ManufacturerSizeMapping $sizeMapping
     * @return void
     */
    public function update(
        ManufacturerSizeMappingUpdate $request,
        Manufacturer $manufacturer,
        $sizeMapping
    ) {
        /** @var ManufacturerSizeMapping $msm */
        $msm = $manufacturer->sizeMappings()->findOrFail($sizeMapping);
        $msm->target_size = $request->input('target_size');
        $msm->save();

        return response()->json(['data' => $msm]);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param Manufacturer $manufacturer
     * @param $sizeMapping
     * @return \Illuminate\Http\JsonResponse
     * @throws \Exception
     */
    public function destroy(Manufacturer $manufacturer, $sizeMapping)
    {
        /** @var ManufacturerSizeMapping $msm */
        $msm = $manufacturer->sizeMappings()->findOrFail($sizeMapping);
        $msm->delete();

        return response()->json('', Response::HTTP_NO_CONTENT);
    }
}
