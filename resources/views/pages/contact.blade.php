@extends('layouts.site')

@section('page')
    <x-page-hero badge="تماس" title="با ما در ارتباط باشید" subtitle="در ۲۴ ساعت پاسخ می‌دهیم — مشاوره کاملاً رایگان است." />

    <section class="landing-section pb-20">
        <div class="landing-container max-w-5xl">
            <x-breadcrumb :items="[
                ['name' => 'خانه', 'url' => route('home')],
                ['name' => 'تماس با ما', 'url' => route('contact')],
            ]" />

            @if (session('success'))
                <div class="mb-6 rounded-xl border border-green-500/30 bg-green-500/10 px-4 py-3 text-green-300">
                    {{ session('success') }}
                </div>
            @endif

            <div class="grid gap-8 lg:grid-cols-5">
                <div class="lg:col-span-2 space-y-4">
                    <div class="glass-card-3d p-6">
                        <h2 class="font-semibold text-white mb-2">ایمیل</h2>
                        <a href="mailto:{{ $contact['email'] }}" class="text-bisan-orange hover:underline">{{ $contact['email'] }}</a>
                    </div>
                    @if (!empty($contact['phone']))
                        <div class="glass-card-3d p-6">
                            <h2 class="font-semibold text-white mb-2">تلفن</h2>
                            <a href="tel:{{ $contact['phone'] }}" class="text-bisan-orange hover:underline" dir="ltr">{{ $contact['phone'] }}</a>
                        </div>
                    @endif
                    <div class="glass-card-3d p-6">
                        <h2 class="font-semibold text-white mb-2">ساعات پاسخگویی</h2>
                        <p class="text-sm text-slate-400">شنبه تا چهارشنبه: ۹ تا ۱۸</p>
                        <p class="text-sm text-slate-400">پنج‌شنبه: ۹ تا ۱۳</p>
                    </div>
                    <div class="glass-card-3d p-6">
                        <h2 class="font-semibold text-white mb-2">زمان پاسخ</h2>
                        <p class="text-sm text-slate-400">پیام‌های فرم تماس حداکثر در ۲۴ ساعت کاری پاسخ داده می‌شوند.</p>
                    </div>
                </div>

                <form method="POST" action="{{ route('contact.store') }}" class="lg:col-span-3 glass-card-3d p-6 space-y-4">
                    @csrf
                    <div>
                        <label for="name" class="block text-sm text-slate-400 mb-1">نام *</label>
                        <input type="text" id="name" name="name" value="{{ old('name') }}" required
                            class="w-full rounded-xl border border-white/10 bg-white/5 px-4 py-3 text-white focus:border-bisan-orange focus:outline-none">
                        @error('name') <p class="mt-1 text-sm text-red-400">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="email" class="block text-sm text-slate-400 mb-1">ایمیل *</label>
                        <input type="email" id="email" name="email" value="{{ old('email') }}" required dir="ltr"
                            class="w-full rounded-xl border border-white/10 bg-white/5 px-4 py-3 text-white focus:border-bisan-orange focus:outline-none">
                        @error('email') <p class="mt-1 text-sm text-red-400">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="phone" class="block text-sm text-slate-400 mb-1">تلفن</label>
                        <input type="text" id="phone" name="phone" value="{{ old('phone') }}" dir="ltr"
                            class="w-full rounded-xl border border-white/10 bg-white/5 px-4 py-3 text-white focus:border-bisan-orange focus:outline-none">
                    </div>
                    <div>
                        <label for="subject" class="block text-sm text-slate-400 mb-1">موضوع</label>
                        <input type="text" id="subject" name="subject" value="{{ old('subject', request('product')) }}"
                            class="w-full rounded-xl border border-white/10 bg-white/5 px-4 py-3 text-white focus:border-bisan-orange focus:outline-none">
                    </div>
                    <div>
                        <label for="message" class="block text-sm text-slate-400 mb-1">پیام *</label>
                        <textarea id="message" name="message" rows="5" required
                            class="w-full rounded-xl border border-white/10 bg-white/5 px-4 py-3 text-white focus:border-bisan-orange focus:outline-none">{{ old('message') }}</textarea>
                        @error('message') <p class="mt-1 text-sm text-red-400">{{ $message }}</p> @enderror
                    </div>
                    <button type="submit" class="btn-primary w-full justify-center">ارسال پیام</button>
                </form>
            </div>

            @if (!empty($faq))
                <div class="mt-16">
                    <x-section-header
                        badge="سوالات متداول"
                        title="پرسش‌های"
                        highlight="رایج"
                    />
                    <div class="grid gap-4 mt-8">
                        @foreach ($faq as $item)
                            <details class="glass-card-3d p-6 group">
                                <summary class="font-semibold text-white cursor-pointer list-none flex items-center justify-between">
                                    {{ $item['q'] }}
                                    <svg class="h-5 w-5 text-slate-400 transition group-open:rotate-180" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                                    </svg>
                                </summary>
                                <p class="mt-4 text-sm leading-7 text-slate-400">{{ $item['a'] }}</p>
                            </details>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    </section>
@endsection
