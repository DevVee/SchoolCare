@extends('errors::minimal')

@php
    $reason = isset($exception) ? trim((string) $exception->getMessage()) : '';
    $showReason = $reason !== '' && $reason !== 'This action is unauthorized.' && $reason !== 'Forbidden';
@endphp

@section('title', 'You do not have access to this page')
@section('code', '403')
@section('message')
    {{ $showReason ? $reason : 'Your account role does not include this page.' }}
    If you need it for your work, ask your administrator to update your role.
@endsection
