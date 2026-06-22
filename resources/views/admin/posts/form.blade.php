@extends('layouts.admin')

@section('title', $post->exists ? 'ویرایش مقاله' : 'مقاله جدید')

@section('content')
<div class="mb-4">
    <a href="{{ route('admin.posts.index') }}" class="text-muted"><i class="ti ti-arrow-right me-1"></i>بازگشت</a>
    <h4 class="mt-2 mb-0">{{ $post->exists ? 'ویرایش: '.$post->title : 'مقاله جدید' }}</h4>
</div>

<form method="POST" action="{{ $post->exists ? route('admin.posts.update', $post) : route('admin.posts.store') }}">
    @csrf
    @if ($post->exists) @method('PUT') @endif

    <div class="card mb-4">
        <div class="card-header"><h5 class="mb-0">محتوا</h5></div>
        <div class="card-body">
            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label class="form-label">عنوان *</label>
                    <input type="text" name="title" value="{{ old('title', $post->title) }}" class="form-control" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Slug *</label>
                    <input type="text" name="slug" value="{{ old('slug', $post->slug) }}" class="form-control" dir="ltr" required>
                </div>
            </div>
            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label class="form-label">دسته‌بندی</label>
                    <select name="category_id" class="form-select">
                        <option value="">بدون دسته</option>
                        @foreach ($categories as $cat)
                            <option value="{{ $cat->id }}" @selected(old('category_id', $post->category_id) == $cat->id)>{{ $cat->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">نویسنده</label>
                    <input type="text" name="author" value="{{ old('author', $post->author ?? 'تیم بیسان') }}" class="form-control">
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label">خلاصه</label>
                <textarea name="excerpt" rows="2" class="form-control" maxlength="500">{{ old('excerpt', $post->excerpt) }}</textarea>
            </div>
            <div class="mb-3">
                <label class="form-label">متن مقاله (HTML)</label>
                <textarea name="body" rows="18" class="form-control font-monospace" dir="ltr">{{ old('body', $post->body) }}</textarea>
            </div>
            <div class="mb-3">
                <label class="form-label">تصویر شاخص (URL)</label>
                <input type="text" name="featured_image" value="{{ old('featured_image', $post->featured_image) }}" class="form-control" dir="ltr">
                <div class="form-text">از <a href="{{ route('admin.media.index') }}">مدیریت رسانه</a> آپلود کنید و URL را کپی کنید.</div>
            </div>
            <div class="row g-3 align-items-end">
                <div class="col-md-6">
                    <label class="form-label">تاریخ انتشار</label>
                    <input type="datetime-local" name="published_at" value="{{ old('published_at', $post->published_at?->format('Y-m-d\TH:i')) }}" class="form-control" dir="ltr">
                </div>
                <div class="col-md-6">
                    <div class="form-check">
                        <input type="checkbox" name="is_published" value="1" class="form-check-input" id="is_published" @checked(old('is_published', $post->is_published))>
                        <label class="form-check-label" for="is_published">منتشر شده</label>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header"><h5 class="mb-0">سئو</h5></div>
        <div class="card-body">
            <div class="mb-3">
                <label class="form-label">عنوان سئو</label>
                <input type="text" name="meta_title" value="{{ old('meta_title', $post->meta_title) }}" class="form-control">
            </div>
            <div class="mb-3">
                <label class="form-label">توضیحات متا</label>
                <textarea name="meta_description" rows="2" class="form-control">{{ old('meta_description', $post->meta_description) }}</textarea>
            </div>
            <div>
                <label class="form-label">کلمات کلیدی</label>
                <input type="text" name="meta_keywords" value="{{ old('meta_keywords', $post->meta_keywords) }}" class="form-control">
            </div>
        </div>
    </div>

    <button type="submit" class="btn btn-primary">ذخیره</button>
</form>
@endsection
