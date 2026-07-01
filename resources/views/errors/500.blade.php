@extends('errors.layout')
@section('title', 'Server error')
@section('code', 'EDU-500')
@section('heading', 'Something went wrong on our side')
@section('message')
    The request hit an unexpected error. It has been logged automatically — your data is safe.
    Please try again; if it keeps happening, send us the reference below.
@endsection
@if (isset($ref))
    @section('reference'){{ $ref }}@endsection
@endif
@section('actions')
    <a class="btn" href="javascript:location.reload()">Try again</a><a class="alt" href="/">Go home</a>
@endsection
