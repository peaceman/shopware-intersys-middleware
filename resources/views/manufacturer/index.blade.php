@extends('layouts/app')

@section('content')
    <div class="container">
        <div class="d-flex border-bottom">
            <h1>{{ __('Manufacturers') }}</h1>
        </div>

        <div class="d-flex p-2 justify-content-end">
            <form class="form-inline" action="{{ route('manufacturers.store') }}" method="POST">
                @csrf

                <div class="form-group mx-2">
                    <label for="inputManufacturerName" class="sr-only">{{ __('Manufacturer name') }}</label>
                    <input type="text" class="form-control mx-2" id="inputManufacturerName" placeholder="{{ __('Manufacturer name') }}" name="name">
                    <small id="manufacturerNameHelpInline" class="text-danger">
                        {{ $errors->first('name') }}
                    </small>
                </div>

                <button type="submit" class="btn btn-primary">{{ __('Create manufacturer') }}</button>
            </form>
        </div>

        <table class="table">
            <thead>
            <tr>
                <th scope="col">#</th>
                <th scope="col">{{ __('Name') }}</th>
                <th scope="col">{{ __('Created at') }}</th>
                <th scope="col">{{ __('Updated at') }}</th>
                <th scope="col">{{ __('Actions') }}</th>
            </tr>
            </thead>
            <tbody>
            @foreach ($manufacturers as $m)
                <tr>
                    <td scope="row">{{ $m->id }}</td>
                    <td>{{ $m->name }}</td>
                    <td>{{ $m->created_at }}</td>
                    <td>{{ $m->updated_at }}</td>
                    <td>
                        <a href="{{ route('manufacturers.show', $m->id) }}">
                            {{ __('View details') }}
                        </a>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>

        {{ $manufacturers->links() }}
    </div>
@endsection
