@extends('layouts.site')

@section('page')
    <x-page-hero
        badge="نیازتان فرق دارد؟"
        title="برای شما"
        highlight="می‌سازیم"
        subtitle="وقتی محصول آماده جواب نمی‌دهد، همان چیزی را می‌سازیم که دقیقاً به کسب‌وکار شما می‌خورد."
    />

    <section class="landing-section pb-20">
        <div class="landing-container">
            <x-breadcrumb :items="[
                ['name' => 'خانه', 'url' => route('home')],
                ['name' => 'پروژه اختصاصی', 'url' => route('services')],
            ]" />

            <div class="grid gap-4 sm:grid-cols-2 lg:gap-6">
                @foreach ($services as $service)
                    <div class="pillar-card pillar-card--glow">
                        <div class="pillar-card__icon">
                            <svg class="h-5 w-5 text-bisan-blue" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="{{ $service['icon'] }}"/>
                            </svg>
                        </div>
                        <div>
                            <h2 class="mb-2 font-semibold text-white">{{ $service['title'] }}</h2>
                            <p class="text-sm leading-7 text-slate-400">{{ $service['desc'] }}</p>
                        </div>
                    </div>
                @endforeach
            </div>

            @if (!empty($process))
                <div class="mt-16">
                    <x-section-header
                        badge="فرآیند کار"
                        title="چطور"
                        highlight="پیش می‌رویم؟"
                        subtitle="از اولین تماس تا تحویل — شفاف و قابل پیگیری."
                    />
                    <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-4 mt-10">
                        @foreach ($process as $step)
                            <div class="glass-card-3d p-6 relative">
                                <span class="text-3xl font-extrabold text-white/10 absolute top-4 left-4">{{ $step['step'] }}</span>
                                <h3 class="font-semibold text-white mb-2 relative">{{ $step['title'] }}</h3>
                                <p class="text-sm leading-7 text-slate-400 relative">{{ $step['desc'] }}</p>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            @if (!empty($industries))
                <div class="mt-16 text-center">
                    <x-section-header
                        badge="صنایع"
                        title="با چه کسب‌وکارهایی"
                        highlight="کار کرده‌ایم؟"
                    />
                    <div class="flex flex-wrap justify-center gap-3 mt-8">
                        @foreach ($industries as $industry)
                            <span class="rounded-full border border-white/10 bg-white/5 px-5 py-2 text-sm text-slate-300">{{ $industry }}</span>
                        @endforeach
                    </div>
                </div>
            @endif

            <div class="mt-16 glass-card-3d p-8 text-center">
                <h3 class="text-xl font-semibold text-white mb-2">ایده دارید؟ بگویید.</h3>
                <p class="text-slate-400 mb-6 max-w-lg mx-auto">نیازتان را توضیح دهید — ما راه‌حل، زمان و هزینه را شفاف می‌گوییم. مشاوره اولیه رایگان است.</p>
                <a href="{{ route('contact') }}" class="btn-primary inline-flex">بگویید چه می‌خواهید — رایگان مشاوره می‌دهیم</a>
            </div>
        </div>
    </section>
@endsection
