@php
 $isFavourite = $entity->isFavourite();
@endphp
<form action="{{ url('/favourites/' . ($isFavourite ? 'remove' : 'add')) }}" method="POST" class="display-inline-item">
    {{ csrf_field() }}
    <input type="hidden" name="type" value="{{ get_class($entity) }}">
    <input type="hidden" name="id" value="{{ $entity->id }}">
    <button type="submit" data-shortcut="favourite" class="icon-list-item text-link page-icon-list" title="{{ $isFavourite ? trans('common.unfavourite') : trans('common.favourite') }}">
        <span>@icon($isFavourite ? 'star' : 'star-outline')</span>
    </button>
</form>