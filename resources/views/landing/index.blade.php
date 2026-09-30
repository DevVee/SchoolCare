{{--
    Product page at "/": what the app is and does, for schools and their staff,
    with a clear path to the school clinic's own page (/clinic).
    Vendor copy: App\Support\ProductSite ($product). Clinic data (status pill,
    links, contact in the footer): App\Services\LandingContent ($page).
--}}
@extends('layouts.public')

@section('document_title', $product['seo']['title'])
@section('body_class', 'lp-body')
@section('bare', '1')

@include('landing.partials.head', [
    'page' => $page,
    'title' => $product['seo']['title'],
    'description' => $product['seo']['description'],
])

@section('header')
    @include('landing.partials.header', ['page' => $page, 'active' => 'product', 'appName' => $product['app']])
@endsection

@section('content')
    @include('landing.product.hero', ['page' => $page, 'product' => $product])
    @include('landing.product.features', ['product' => $product])
    @include('landing.product.steps', ['product' => $product])
    @include('landing.product.cta', ['product' => $product])
@endsection

@section('footer')
    @include('landing.partials.footer', ['page' => $page, 'about' => $product['footer']['about']])
@endsection
