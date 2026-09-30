{{--
    The school clinic's own page at "/clinic": today's hours with a live open or
    closed indicator, then the sections chosen and ordered in Administration >
    Website (advisories, services, hours, how to get care, team, FAQ, contact).
    All text comes from there (App\Services\LandingContent::page()).
--}}
@extends('layouts.public')

@section('document_title', $page['seo']['title'])
@section('body_class', 'lp-body lp-clinic')
@section('bare', '1')

@include('landing.partials.head', [
    'page' => $page,
    'image' => $page['hero']['image']['url'] ?? null,
])

@section('header')
    @include('landing.partials.header', ['page' => $page, 'active' => 'clinic', 'clinicBrand' => true, 'subnav' => true])
@endsection

@section('content')
    @include('landing.partials.hero', ['page' => $page])

    @php $tinted = false; @endphp
    @foreach ($page['sections'] as $section)
        @php $tinted = ! $tinted; @endphp
        @include('landing.sections.'.$section, ['page' => $page, 'tinted' => $tinted])
    @endforeach
@endsection

@section('footer')
    @include('landing.partials.footer', ['page' => $page, 'clinicBrand' => true])
@endsection
