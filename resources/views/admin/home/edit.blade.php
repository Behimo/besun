@extends('layouts.admin')

@section('title', 'صفحه اصلی')

@section('content')
<div class="mb-4">
    <h4 class="mb-1">ویرایش صفحه اصلی</h4>
    <p class="text-muted mb-0">محتوای هیرو، آمار، نظرات مشتریان و بخش‌های صفحه اول</p>
</div>

<form method="POST" action="{{ route('admin.home.update') }}">
    @csrf @method('PUT')

    <div class="card mb-4">
        <div class="card-header"><h5 class="mb-0">بخش هیرو</h5></div>
        <div class="card-body">
            <div class="mb-3">
                <label class="form-label">متن بالای عنوان</label>
                <input type="text" name="hero_eyebrow" value="{{ old('hero_eyebrow', $content['hero']['eyebrow'] ?? '') }}" class="form-control">
            </div>
            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label class="form-label">خط اول عنوان</label>
                    <input type="text" name="hero_title_line1" value="{{ old('hero_title_line1', $content['hero']['title_line1'] ?? '') }}" class="form-control">
                </div>
                <div class="col-md-6">
                    <label class="form-label">خط آخر عنوان</label>
                    <input type="text" name="hero_title_line2" value="{{ old('hero_title_line2', $content['hero']['title_line2'] ?? '') }}" class="form-control">
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label">کلمات چرخشی (هر خط یک کلمه)</label>
                <textarea name="hero_rotate_words" rows="3" class="form-control">{{ old('hero_rotate_words', implode("\n", $content['hero']['rotate_words'] ?? [])) }}</textarea>
            </div>
            <div class="mb-3">
                <label class="form-label">متن توضیح</label>
                <textarea name="hero_lead" rows="3" class="form-control">{{ old('hero_lead', $content['hero']['lead'] ?? '') }}</textarea>
            </div>
            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label class="form-label">دکمه اصلی</label>
                    <input type="text" name="hero_cta_primary" value="{{ old('hero_cta_primary', $content['hero']['cta_primary'] ?? '') }}" class="form-control">
                </div>
                <div class="col-md-6">
                    <label class="form-label">دکمه ثانویه</label>
                    <input type="text" name="hero_cta_secondary" value="{{ old('hero_cta_secondary', $content['hero']['cta_secondary'] ?? '') }}" class="form-control">
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label">برچسب‌های اعتماد (هر خط یک مورد)</label>
                <textarea name="hero_chips" rows="3" class="form-control">{{ old('hero_chips', implode("\n", $content['hero']['chips'] ?? [])) }}</textarea>
            </div>
            @for ($i = 0; $i < 3; $i++)
                <div class="row g-3 border-top pt-3 mb-3">
                    <div class="col-md-6">
                        <label class="form-label">کارت {{ $i + 1 }} — عنوان</label>
                        <input type="text" name="hero_pills[{{ $i }}][title]" value="{{ old("hero_pills.$i.title", $content['hero']['pills'][$i]['title'] ?? '') }}" class="form-control">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">کارت {{ $i + 1 }} — متن</label>
                        <input type="text" name="hero_pills[{{ $i }}][text]" value="{{ old("hero_pills.$i.text", $content['hero']['pills'][$i]['text'] ?? '') }}" class="form-control">
                    </div>
                </div>
            @endfor
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header"><h5 class="mb-0">آمار</h5></div>
        <div class="card-body">
            @for ($i = 0; $i < 4; $i++)
                <div class="row g-3 mb-3">
                    <div class="col-md-4">
                        <input type="text" name="stats[{{ $i }}][value]" placeholder="مقدار" value="{{ old("stats.$i.value", $content['stats'][$i]['value'] ?? '') }}" class="form-control">
                    </div>
                    <div class="col-md-4">
                        <input type="text" name="stats[{{ $i }}][label]" placeholder="برچسب" value="{{ old("stats.$i.label", $content['stats'][$i]['label'] ?? '') }}" class="form-control">
                    </div>
                    <div class="col-md-4">
                        <input type="text" name="stats[{{ $i }}][hint]" placeholder="توضیح کوتاه" value="{{ old("stats.$i.hint", $content['stats'][$i]['hint'] ?? '') }}" class="form-control">
                    </div>
                </div>
            @endfor
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header"><h5 class="mb-0">همکاران / برندها</h5></div>
        <div class="card-body">
            <textarea name="partners" rows="4" class="form-control" placeholder="هر خط یک نام برند">{{ old('partners', implode("\n", $content['partners'] ?? [])) }}</textarea>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header"><h5 class="mb-0">نظرات مشتریان</h5></div>
        <div class="card-body">
            @for ($i = 0; $i < 3; $i++)
                <div class="border-top pt-3 mb-3">
                    <div class="row g-3 mb-2">
                        <div class="col-md-6">
                            <input type="text" name="testimonials[{{ $i }}][name]" placeholder="نام" value="{{ old("testimonials.$i.name", $content['testimonials'][$i]['name'] ?? '') }}" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <input type="text" name="testimonials[{{ $i }}][role]" placeholder="سمت" value="{{ old("testimonials.$i.role", $content['testimonials'][$i]['role'] ?? '') }}" class="form-control">
                        </div>
                    </div>
                    <textarea name="testimonials[{{ $i }}][text]" rows="2" placeholder="متن نظر" class="form-control">{{ old("testimonials.$i.text", $content['testimonials'][$i]['text'] ?? '') }}</textarea>
                </div>
            @endfor
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header"><h5 class="mb-0">بخش CTA پایانی</h5></div>
        <div class="card-body">
            <input type="text" name="cta_title" value="{{ old('cta_title', $content['cta']['title'] ?? '') }}" class="form-control mb-3" placeholder="عنوان">
            <textarea name="cta_subtitle" rows="2" class="form-control" placeholder="زیرعنوان">{{ old('cta_subtitle', $content['cta']['subtitle'] ?? '') }}</textarea>
        </div>
    </div>

    <button type="submit" class="btn btn-primary">ذخیره صفحه اصلی</button>
</form>
@endsection
