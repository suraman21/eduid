@extends('errors.layout')
@section('title', 'Not found')
@section('code', 'EDU-404')
@section('heading', 'Page not found')
@section('message')
    The page you’re looking for doesn’t exist or may have been moved.
@endsection
@section('actions')
    <a class="btn" href="/">Go home</a>
@endsection
