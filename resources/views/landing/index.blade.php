{{--
    Public website home page. All text, sections and their order come from
    Administration → Website (App\Services\LandingContent::page()).
--}}
@extends('layouts.public')

@section('document_title', $page['seo']['title'])
@section('body_class', 'lp-body')
@section('bare', '1')

@include('landing.partials.head', ['page' => $page])

@section('header')
    @include('landing.partials.header', ['page' => $page, 'base' => ''])
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
    @include('landing.partials.footer', ['page' => $page, 'base' => ''])
@endsection
