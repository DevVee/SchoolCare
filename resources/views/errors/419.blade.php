@extends('errors::minimal')

{{-- Sessions end after a period of inactivity or a server restart. Send the user to sign in. --}}
@section('head')
    <meta http-equiv="refresh" content="5; url={{ url('/login') }}">
@endsection

@section('title', 'Your session has ended')
@section('code', '419')
@section('message', 'For your security you were signed out after a period of inactivity. Nothing you already saved was lost. Sign in again to continue.')

@section('actions')
    <a href="{{ url('/login') }}" class="btn btn-primary">
        <i class="bi bi-box-arrow-in-right" aria-hidden="true"></i>Sign in again
    </a>
@endsection

@section('hint', 'Taking you to the sign-in page in 5 seconds.')
