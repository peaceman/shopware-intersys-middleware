<?php

namespace App\Http\Controllers;

use App\Http\Requests\ManufacturerStore;
use App\Manufacturer;

class ManufacturerController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $manufacturers = Manufacturer::query()->paginate();

        return view('manufacturer.index', ['manufacturers' => $manufacturers]);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  ManufacturerStore  $request
     * @return \Illuminate\Http\Response
     */
    public function store(ManufacturerStore $request)
    {
        $manufacturer = new Manufacturer();
        $manufacturer->name = $request->input('name');
        $manufacturer->save();

        return back();
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Manufacturer  $manufacturer
     * @return \Illuminate\Http\Response
     */
    public function show(Manufacturer $manufacturer)
    {
        return view('manufacturer.show', ['manufacturer' => $manufacturer]);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Manufacturer $manufacturer
     * @return \Illuminate\Http\Response
     * @throws \Exception
     */
    public function destroy(Manufacturer $manufacturer)
    {
        $manufacturer->delete();

        return redirect()->route('manufacturers.index');
    }
}
