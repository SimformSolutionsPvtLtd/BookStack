@extends('layouts.simple')

@section('body')

    <div class="container small">

        <div class="my-s">
            @include('entities.breadcrumbs', [
                'crumbs' => [
                    $shelf,
                    $shelf->getUrl('/copy') => [
                        'text' => trans('entities.books_copy'),
                        'icon' => 'copy',
                    ],
                ],
            ])
        </div>

        <div class="card content-wrap auto-height">

            <h1 class="list-heading">{{ trans('entities.books_copy_multiple') }}</h1>

            <form action="{{ $shelf->getUrl('/copy') }}" method="POST">
                {!! csrf_field() !!}

                <div class="form-group">
                    <label for="name">{{ trans('entities.select_shelves') }}</label>
                    <select id="shelves" name="shelves" class="input-fill-width">
                        <option>{{ trans('entities.select_shelves') }}</option>
                        @foreach($bookshelf as $option)
                            <option value="{{$option->slug}}"class="text-neg">
                                {{ $option->name }}
                            </option>
                        @endforeach
                    </select>
                    
                    @if($errors->has('shelves'))
                        <div class="text-neg text-small">{{ $errors->first('shelves') }}</div>
                    @endif
                </div>

                @include('common.shelf-sort', [
                    'shelf' => null,
                    'books' => $books,
                    'shelfLabelOne' => 'entities.selected_books_to_copy',
                    'shelfLabelOneDrag' => 'entities.filter_books',
                    'shelfLabelTwo' => 'entities.select_book_to_copy',
                ])
                @if($errors->has('books'))
                    <div class="text-neg text-small">{{ $errors->first('books') }}</div>
                @endif
           

                @include('entities.copy-considerations')

                <div class="form-group text-right">
                    <a href="{{ $shelf->getUrl() }}" class="button outline">{{ trans('common.cancel') }}</a>
                    <button type="submit" class="button">{{ trans('entities.books_copy_multiple') }}</button>
                </div>
            </form>

        </div>
    </div>

@stop

