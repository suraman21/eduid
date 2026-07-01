@extends('errors.layout')
@section('title', 'Access denied')
@section('code', 'EDU-403')
@section('heading', 'You don’t have access to this page')
@section('message')
    Your account doesn’t have permission for this area. If you were signed in to a
    different portal in another tab, sign in to the right one and try again.
@endsection
@section('actions')
    <a class="btn" href="/">Go home</a><a class="alt" href="/login">School login</a><a class="alt" href="/admin/login">Admin login</a>
@endsection
