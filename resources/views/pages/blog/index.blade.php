@extends('layouts.site')

@section('page')
    <section class="landing-section pt-28 pb-16">
        <div class="landing-container">
            <x-section-header
                badge="بلاگ بیسان"
                title="مقالات و"
                highlight="راهنماها"
                subtitle="نکات کاربردی درباره CRM، مدیریت فروش و رشد کسب‌وکار."
            />

            @if ($posts->isEmpty())
                <div class="mt-12 text-center glass-card-3d p-12">
                    <p class="text-slate-400">به‌زودی مقالات جدید منتشر می‌شود.</p>
                </div>
            @else
                <div class="grid gap-8 md:grid-cols-2 lg:grid-cols-3 mt-12">
                    @foreach ($posts as $post)
                        <article class="blog-card glass-card-3d overflow-hidden group">
                            <a href="{{ route('blog.show', $post->slug) }}" class="block">
                                @if ($post->featured_image)
                                    <div class="aspect-[16/10] overflow-hidden">
                                        <img src="{{ $post->featured_image }}" alt="{{ $post->title }}" class="h-full w-full object-cover transition duration-500 group-hover:scale-105">
                                    </div>
                                @else
                                    <div class="aspect-[16/10] bg-gradient-to-br from-bisan-orange/15 to-bisan-purple/15 flex items-center justify-center">
                                        <span class="text-5xl font-extrabold text-white/10">B</span>
                                    </div>
                                @endif
                                <div class="p-6">
                                    <div class="flex items-center gap-3 text-xs text-slate-500 mb-3">
                                        @if ($post->category)
                                            <span class="text-bisan-orange">{{ $post->category->name }}</span>
                                            <span>·</span>
                                        @endif
                                        <time>{{ $post->published_at?->format('Y/m/d') }}</time>
                                    </div>
                                    <h2 class="text-lg font-bold text-white group-hover:text-bisan-orange-light transition">{{ $post->title }}</h2>
                                    <p class="mt-3 text-sm leading-7 text-slate-400 line-clamp-3">{{ $post->excerpt }}</p>
                                    <span class="mt-4 inline-flex items-center gap-1 text-sm text-bisan-orange">ادامه مطلب
                                        <svg class="h-4 w-4 rotate-180" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg>
                                    </span>
                                </div>
                            </a>
                        </article>
                    @endforeach
                </div>

                <div class="mt-12">{{ $posts->links() }}</div>
            @endif
        </div>
    </section>
@endsection
