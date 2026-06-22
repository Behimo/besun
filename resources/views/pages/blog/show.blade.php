@extends('layouts.site')

@section('page')
    <article class="landing-section pt-28 pb-16">
        <div class="landing-container max-w-4xl">
            <nav class="text-sm text-slate-500 mb-6">
                <a href="{{ route('home') }}" class="hover:text-white">خانه</a>
                <span class="mx-2">/</span>
                <a href="{{ route('blog.index') }}" class="hover:text-white">بلاگ</a>
                <span class="mx-2">/</span>
                <span class="text-slate-400">{{ $post->title }}</span>
            </nav>

            @if ($post->category)
                <span class="text-sm text-bisan-orange">{{ $post->category->name }}</span>
            @endif

            <h1 class="section-heading text-white mt-2 mb-4">{{ $post->title }}</h1>

            <div class="flex flex-wrap items-center gap-4 text-sm text-slate-500 mb-8 pb-8 border-b border-white/10">
                @if ($post->author)
                    <span>{{ $post->author }}</span>
                @endif
                @if ($post->published_at)
                    <time>{{ $post->published_at->format('Y/m/d') }}</time>
                @endif
                <span>{{ number_format($post->views) }} بازدید</span>
            </div>

            @if ($post->featured_image)
                <div class="mb-10 rounded-2xl overflow-hidden">
                    <img src="{{ $post->featured_image }}" alt="{{ $post->title }}" class="w-full">
                </div>
            @endif

            <div class="prose-blog text-slate-300 leading-8 space-y-4">
                {!! $post->body !!}
            </div>

            @if ($related->isNotEmpty())
                <section class="mt-16 pt-10 border-t border-white/10">
                    <h2 class="text-xl font-bold text-white mb-6">مقالات مرتبط</h2>
                    <div class="grid gap-4 sm:grid-cols-3">
                        @foreach ($related as $item)
                            <a href="{{ route('blog.show', $item->slug) }}" class="glass-card-3d p-4 hover:border-bisan-orange/30 transition block">
                                <h3 class="font-medium text-white text-sm">{{ $item->title }}</h3>
                            </a>
                        @endforeach
                    </div>
                </section>
            @endif
        </div>
    </article>
@endsection
