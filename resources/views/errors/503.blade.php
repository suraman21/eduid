@extends('errors.layout')
@section('title', 'Maintenance')
@section('code', 'EDU-503')
@section('heading', 'Back in a few minutes')
@section('message')
    EduID is undergoing a quick scheduled update. Your data is safe — please try again shortly.
@endsection
@section('actions')
    <a class="btn" href="javascript:location.reload()">Retry</a>
@endsection
