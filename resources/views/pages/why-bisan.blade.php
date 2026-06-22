@extends('layouts.site')

@section('page')
    <x-page-hero badge="مزایا" title="چرا بیسان؟" subtitle="۴ دلیل که بیش از ۱۲۰۰ کسب‌وکار ما را انتخاب کرده‌اند." />

    <section class="landing-section pb-20">
        <div class="landing-container">
            <x-breadcrumb :items="[
                ['name' => 'خانه', 'url' => route('home')],
                ['name' => 'چرا بیسان؟', 'url' => route('why-bisan')],
            ]" />

            <x-why-bisan :items="$whyBisan" />

            <div class="mt-16 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                <div class="glass-card-3d p-6 text-center">
                    <p class="text-3xl font-extrabold text-bisan-orange mb-2">+۱۲۰۰</p>
                    <p class="text-sm text-slate-300">کسب‌وکار فعال</p>
                </div>
                <div class="glass-card-3d p-6 text-center">
                    <p class="text-3xl font-extrabold text-purple-400 mb-2">+۱۰</p>
                    <p class="text-sm text-slate-300">سال تجربه</p>
                </div>
                <div class="glass-card-3d p-6 text-center sm:col-span-2 lg:col-span-1">
                    <p class="text-3xl font-extrabold text-blue-400 mb-2">۳</p>
                    <p class="text-sm text-slate-300">محصول آماده</p>
                </div>
            </div>

            <div class="mt-12 text-center">
                <p class="text-slate-400 mb-6">آماده‌اید ببینید کدام محصول یا پروژه برای شما مناسب است؟</p>
                <div class="flex flex-wrap justify-center gap-4">
                    <a href="{{ route('products.index') }}" class="btn-outline">مشاهده محصولات</a>
                    <a href="{{ route('contact') }}" class="btn-primary">مشاوره رایگان</a>
                </div>
            </div>
        </div>
    </section>
@endsection
