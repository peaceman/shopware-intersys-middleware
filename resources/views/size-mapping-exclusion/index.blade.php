@extends('layouts/app')

@section('content')
    <div class="container">
        <div class="d-flex border-bottom">
            <h1>{{ __('SizeMapping Exclusions') }}</h1>
        </div>

        <div class="d-flex p-2 justify-content-end">
            <form class="form-inline" action="{{ route('size-mapping-exclusions.store') }}" method="POST">
                @csrf

                <div class="form-group mx-2">
                    <label for="inputArticleNumber" class="sr-only">{{ __('Article number') }}</label>
                    <input type="text" class="form-control mx-2" id="inputArticleNumber" placeholder="{{ __('Article number') }}" name="article_number">
                    <small id="articleNumberHelpInline" class="text-danger">
                        {{ $errors->first('article_number') }}
                    </small>
                </div>

                <button type="submit" class="btn btn-primary">{{ __('Create size mapping exclusion') }}</button>
            </form>
        </div>

        <table class="table">
            <thead>
            <tr>
                <th scope="col">#</th>
                <th scope="col">{{ __('Article number') }}</th>
                <th scope="col">{{ __('Created at') }}</th>
                <th scope="col">{{ __('Actions') }}</th>
            </tr>
            </thead>
            <tbody>
            @foreach ($sizeMappingExclusions as $sme)
                <tr>
                    <td scope="row">{{ $sme->id }}</td>
                    <td>{{ $sme->article_number }}</td>
                    <td>{{ $sme->created_at }}</td>
                    <td>
                        <form action="{{ route('size-mapping-exclusions.destroy', $sme) }}" method="POST">
                            @method('DELETE')
                            @csrf
                            <button class="btn btn-danger">{{ __('Delete') }}</button>
                        </form>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>

        {{ $sizeMappingExclusions->links() }}
    </div>
@endsection
