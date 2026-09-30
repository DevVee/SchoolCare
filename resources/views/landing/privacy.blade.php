{{-- Privacy notice (Data Privacy Act), text from Administration → Website. --}}
@extends('layouts.public')

@section('document_title', 'Privacy notice | '.$page['names']['clinic'])
@section('body_class', 'lp-body lp-clinic')
@section('bare', '1')

@include('landing.partials.head', [
    'page' => $page,
    'description' => 'How the '.$page['names']['clinic'].' collects, uses and protects the health information of students and staff.',
])

@section('header')
    @include('landing.partials.header', ['page' => $page, 'clinicBrand' => true])
@endsection

@section('content')
    <section class="lp-section" aria-labelledby="lp-privacy-title">
        <div class="lp-container">
            <article class="lp-doc">
                <a href="{{ route('clinic') }}" class="lp-link"><x-ui.icon name="chevron-left" />Back to the clinic page</a>
                <h1 id="lp-privacy-title">Privacy notice</h1>
                <p class="lp-lead">How the {{ $page['names']['clinic'] }}{{ $page['names']['school'] !== '' ? ' of '.$page['names']['school'] : '' }} handles your health information.</p>
                <div class="lp-doc-body">
                    @forelse ($page['privacy'] as $paragraph)
                        <p>{{ $paragraph }}</p>
                    @empty
                        <p>The clinic has not published its privacy notice yet. For questions about your clinic record, please visit or contact the clinic.</p>
                    @endforelse
                </div>
            </article>
        </div>
    </section>
@endsection

@section('footer')
    @include('landing.partials.footer', ['page' => $page, 'clinicBrand' => true])
@endsection
