@extends('layouts.admin')

@section('title', $product->exists ? 'ویرایش محصول' : 'محصول جدید')

@section('content')
<div class="mb-4">
    <a href="{{ route('admin.products.index') }}" class="text-muted"><i class="ti ti-arrow-right me-1"></i>بازگشت</a>
    <h4 class="mt-2 mb-0">{{ $product->exists ? 'ویرایش: '.$product->title : 'محصول جدید' }}</h4>
</div>

<form method="POST" action="{{ $product->exists ? route('admin.products.update', $product) : route('admin.products.store') }}" class="card" style="max-width: 48rem;">
    @csrf
    @if ($product->exists) @method('PUT') @endif
    <div class="card-body">
        <div class="row g-3 mb-3">
            <div class="col-md-6">
                <label class="form-label">عنوان *</label>
                <input type="text" name="title" value="{{ old('title', $product->title) }}" class="form-control" required>
            </div>
            <div class="col-md-6">
                <label class="form-label">Slug *</label>
                <input type="text" name="slug" value="{{ old('slug', $product->slug) }}" class="form-control" dir="ltr" required>
            </div>
        </div>

        <div class="row g-3 mb-3">
            <div class="col-md-6">
                <label class="form-label">زیرعنوان</label>
                <input type="text" name="subtitle" value="{{ old('subtitle', $product->subtitle) }}" class="form-control">
            </div>
            <div class="col-md-6">
                <label class="form-label">رنگ</label>
                <select name="accent" class="form-select">
                    @foreach (['orange', 'purple', 'blue'] as $accent)
                        <option value="{{ $accent }}" @selected(old('accent', $product->accent) === $accent)>{{ $accent }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="mb-3">
            <label class="form-label">توضیح کوتاه</label>
            <textarea name="description" rows="2" class="form-control">{{ old('description', $product->description) }}</textarea>
        </div>
        <div class="mb-3">
            <label class="form-label">مخاطب</label>
            <input type="text" name="audience" value="{{ old('audience', $product->audience) }}" class="form-control">
        </div>
        <div class="mb-3">
            <label class="form-label">ویژگی‌ها (هر خط یک مورد)</label>
            <textarea name="features" rows="4" class="form-control">{{ old('features', is_array($product->features) ? implode("\n", $product->features) : '') }}</textarea>
        </div>
        <div class="mb-3">
            <label class="form-label">متن کامل صفحه</label>
            <textarea name="body" rows="6" class="form-control">{{ old('body', $product->body) }}</textarea>
        </div>
        <div class="row g-3 mb-3">
            <div class="col-md-6">
                <label class="form-label">دکمه CTA</label>
                <input type="text" name="cta" value="{{ old('cta', $product->cta) }}" class="form-control">
            </div>
            <div class="col-md-6">
                <label class="form-label">ترتیب</label>
                <input type="number" name="sort_order" value="{{ old('sort_order', $product->sort_order ?? 0) }}" class="form-control" min="0">
            </div>
        </div>

        <hr>
        <h5 class="mb-3">سئو</h5>
        <div class="mb-3">
            <label class="form-label">عنوان سئو</label>
            <input type="text" name="meta_title" value="{{ old('meta_title', $product->meta_title) }}" class="form-control">
        </div>
        <div class="mb-3">
            <label class="form-label">توضیحات متا</label>
            <textarea name="meta_description" rows="2" class="form-control">{{ old('meta_description', $product->meta_description) }}</textarea>
        </div>
        <div class="mb-3">
            <label class="form-label">کلمات کلیدی</label>
            <input type="text" name="meta_keywords" value="{{ old('meta_keywords', $product->meta_keywords) }}" class="form-control">
        </div>
        <div class="mb-3">
            <label class="form-label">تصویر OG</label>
            <input type="text" name="og_image" value="{{ old('og_image', $product->og_image) }}" class="form-control" dir="ltr">
        </div>

        <div class="d-flex gap-4 mb-4">
            <div class="form-check">
                <input type="checkbox" name="is_published" value="1" class="form-check-input" id="is_published" @checked(old('is_published', $product->is_published ?? true))>
                <label class="form-check-label" for="is_published">منتشر شده</label>
            </div>
            <div class="form-check">
                <input type="checkbox" name="is_featured" value="1" class="form-check-input" id="is_featured" @checked(old('is_featured', $product->is_featured))>
                <label class="form-check-label" for="is_featured">ویژه</label>
            </div>
        </div>

        <button type="submit" class="btn btn-primary">ذخیره</button>
    </div>
</form>
@endsection
