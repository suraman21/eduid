@extends('errors.layout')
@section('title', 'Session expired')
@section('code', 'EDU-419')
@section('heading', 'Your session expired')
@section('message')
    You were inactive for a while (or signed in elsewhere), so this page’s security
    token is no longer valid. Sign in again to continue — nothing is wrong with your account.
@endsection
@section('actions')
    <a class="btn" href="/login">School login</a><a class="alt" href="/admin/login">Admin login</a>
@endsection
