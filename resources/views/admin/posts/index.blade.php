@extends('layouts.admin')

@section('title', 'بلاگ')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
    <div>
        <h4 class="mb-1">مقالات بلاگ</h4>
        <p class="text-muted mb-0">مدیریت مقالات و انتشارات</p>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('admin.categories.index') }}" class="btn btn-label-secondary">دسته‌بندی‌ها</a>
        <a href="{{ route('admin.posts.create') }}" class="btn btn-primary">
            <i class="ti ti-plus me-1"></i>مقاله جدید
        </a>
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover">
            <thead>
                <tr>
                    <th>عنوان</th>
                    <th>دسته</th>
                    <th>تاریخ</th>
                    <th>وضعیت</th>
                    <th>عملیات</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($posts as $post)
                    <tr>
                        <td>
                            <div class="fw-medium">{{ $post->title }}</div>
                            <small class="text-muted" dir="ltr">/blog/{{ $post->slug }}</small>
                        </td>
                        <td>{{ $post->category?->name ?? '—' }}</td>
                        <td class="text-muted">{{ $post->published_at?->format('Y/m/d') ?? '—' }}</td>
                        <td>
                            @if ($post->is_published)
                                <span class="badge bg-label-success">منتشر</span>
                            @else
                                <span class="badge bg-label-warning">پیش‌نویس</span>
                            @endif
                        </td>
                        <td>
                            <a href="{{ route('admin.posts.edit', $post) }}" class="btn btn-sm btn-label-primary">ویرایش</a>
                            <form method="POST" action="{{ route('admin.posts.destroy', $post) }}" class="d-inline" onsubmit="return confirm('حذف شود؟')">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-label-danger">حذف</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center text-muted py-5">مقاله‌ای ثبت نشده است.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="mt-4">{{ $posts->links() }}</div>
@endsection
