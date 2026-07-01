@extends('errors.layout')
@section('title', 'Too many requests')
@section('code', 'EDU-429')
@section('heading', 'Slow down a moment')
@section('message')
    Too many requests in a short time. Wait a minute and try again.
@endsection
@section('actions')
    <a class="btn" href="/">Go home</a>
@endsection
