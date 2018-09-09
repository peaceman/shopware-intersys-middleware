@extends('layouts/app')

@section('content')
    <div class="container">
        <div class="d-flex justify-content-between border-bottom my-2">
            <h1>{{ __('Manufacturer') }}: {{ $manufacturer->name }}</h1>

            <form action="{{ route('manufacturers.destroy', $manufacturer) }}" method="POST">
                @method('DELETE')
                @csrf
                <button class="btn btn-danger">{{ __('Delete') }}</button>
            </form>
        </div>

        <div class="my-2">
            <manufacturer-size-mappings :manufacturer-id="{{ $manufacturer->id }}"></manufacturer-size-mappings>
        </div>
    </div>
@endsection
