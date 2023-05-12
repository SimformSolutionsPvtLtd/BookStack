@extends('layouts.simple')

@section('body')
<div class="container">

    <div class="card content-wrap auto-height m-5">
        <h1 class="list-heading">{{ trans('entities.notifications') }}</h1>
        <div class="clear-all-link">
            @if(count($notifications))
                <a href="{{url('clear-notifications')}}" class="btn btn-primary">Clear All</a>
            @endif
        </div>
        <hr class="mt-m mb-s">
        <div class="container display-inline-flex">
            <img src="{{ asset('notification.svg') }}" alt="SVG Image" style="height:300px">

            <div>
                @forelse ($notifications as $notification)
                <p> <img src="{{ asset('bell.png') }}" alt="Bell" class="notification-bell"> <span class="notification-text">{{$notification->data['message']}}</span> <a href="{{getUrl($notification->data['module_id'],$notification->data['type'])}}" class="btn btn-primary" style="text-decoration:none"> View </a></p>

                <hr class="mt-m mb-s">

                @empty
                <h4>Notifications not found !</h4>
                @endforelse
                <div class="text-center">
                    {!! $notifications->links() !!}
                </div>
            </div>
        </div>

    </div>
</div>
@stop
