@extends('layouts.simple')

@section('body')
<div class="container">

    <div class="my-s">
        @include('entities.breadcrumbs', ['crumbs' => [
        $book,
        $book->getUrl('/delete') => [
        'text' => trans('entities.change_status'),
        'icon' => 'book',
        ]
        ]])
    </div>
    <div class="card content-wrap auto-height">

        <h1 class="list-heading">{{ trans('entities.change_status') }}</h1>

        <form action="{{ $book->getUrl('/change-status') }}" method="POST">
            {!! csrf_field() !!}

            <div class="flex-container-rows">
            <div class="form-group">
                <label for="owner">{{ trans('entities.status') }}</label>
                <select class="input-fill-width" name="status" id="status">
                    <option value="">{{ trans('entities.select_status') }}</option>
                    @foreach($enums as $value)
                    <option value="{{ $value}}" @if($value==$book->status) selected @endif>{{ $value }}</option>
                    @endforeach
                </select>
                @if($errors->has('status'))
                   <div class="text-neg text-small">{{ $errors->first('status') }}</div>
                @endif
            </div>

            <div class="form-group">
                <label for="name">{{ trans('entities.status_reason') }}</label>
                <input type="text" class="input-fill-width" name="status_reason" id="status_reason">
                @if($errors->has('status_reason'))
                    <div class="text-neg text-small">{{ $errors->first('status_reason') }}</div>
                @endif
            </div>
            </div>

            <div class="form-group text-right">
                <a href="{{ $book->getUrl() }}" class="button outline">{{ trans('common.cancel') }}</a>
                <button type="submit" class="button" @if($book->status === $book::APPROVED_BY_CLIENT) disabled @endif>{{ trans('entities.change_status') }}</button>
            </div>
        </form>

        <h1 class="list-heading">{{ trans('settings.status_log') }}</h1>
        <p class="text-muted">{{ trans('settings.status_log_detail') }}</p>

        <hr class="mt-m mb-s">
        <div class="item-list">
            <div class="item-list-row flex-container-row items-center bold hide-under-m">
                <div class="flex-2 px-m py-xs flex-container-row items-center">{{ trans('settings.audit_table_user') }}</div>
                <div class="flex-2 px-m py-xs">{{ trans('entities.status') }}</div>
                <div class="flex-3 px-m py-xs">{{ trans('entities.status_reason') }}</div>
                <div class="flex-container-row flex-3">
                    <div class="flex-2 px-m py-xs text-right">{{ trans('settings.audit_table_date') }}</div>
                </div>
            </div>
            @foreach($activities as $activity)
                <div class="item-list-row flex-container-row items-center wrap py-xxs">
                    <div class="flex-2 px-m py-xxs flex-container-row items-center min-width-m">
                        @include('settings.parts.table-user', ['user' => $activity->user, 'user_id' => $activity->user_id])
                    </div>
                    <div class="flex-2 px-m py-xxs min-width-m"><strong class="mr-xs hide-over-m">{{ trans('settings.audit_table_event') }}:</strong> {{ $activity->detail }}</div>
                    <div class="flex-3 px-m py-xxs min-width-l">
                            <div>{{ $activity->status_reason ? $activity->status_reason : 'N/A' }}</div>
                    </div>
                    <div class="flex-container-row flex-3">
                        <div class="flex-2 px-m py-xxs text-m-right min-width-xs"><strong class="mr-xs hide-over-m">{{ trans('settings.audit_table_date') }}:<br></strong> {{ $activity->created_at }}</div>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="py-m">
            {{ $activities->links() }}
        </div>
   
    </div>
</div>
@stop
