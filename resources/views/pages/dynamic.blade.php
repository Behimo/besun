@extends('layouts.site')

@section('page')
    <section class="landing-section pt-28 pb-16">
        <div class="landing-container max-w-4xl">
            <nav class="text-sm text-slate-500 mb-6">
                <a href="{{ route('home') }}" class="hover:text-white">خانه</a>
                <span class="mx-2">/</span>
                <span class="text-slate-400">{{ $page->title }}</span>
            </nav>

            <h1 class="section-heading text-white mb-8">{{ $page->title }}</h1>

            <div class="prose-blog glass-card-3d p-8 lg:p-10 text-slate-300 leading-8">
                {!! $bodyHtml !!}
            </div>
        </div>
    </section>
@endsection
