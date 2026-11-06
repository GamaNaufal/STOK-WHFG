@extends('shared.layouts.app')
@section('styles')
    <link rel="stylesheet" href="{{ asset('css/pages/export-delivery-plans.css') }}">
    <link rel="stylesheet" href="{{ asset('css/pages/delivery-announcements.css') }}">
@endsection
@section('content')
<div class="delivery-plan-page announcement-page">
    @yield('announcement-content')
</div>
@endsection
