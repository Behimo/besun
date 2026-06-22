@extends('layouts.admin')

@section('title', 'دسته‌بندی‌ها')

@section('content')
<div class="mb-4">
    <a href="{{ route('admin.posts.index') }}" class="text-muted"><i class="ti ti-arrow-right me-1"></i>بازگشت به بلاگ</a>
    <h4 class="mt-2 mb-0">دسته‌بندی‌های بلاگ</h4>
</div>

<div class="card mb-4" style="max-width: 36rem;">
    <div class="card-header"><h5 class="mb-0">دسته جدید</h5></div>
    <div class="card-body">
        <form method="POST" action="{{ route('admin.categories.store') }}">
            @csrf
            <div class="row g-3 mb-3">
                <div class="col-sm-6">
                    <label class="form-label">نام</label>
                    <input type="text" name="name" class="form-control" required>
                </div>
                <div class="col-sm-6">
                    <label class="form-label">Slug</label>
                    <input type="text" name="slug" class="form-control" dir="ltr" required>
                </div>
            </div>
            <button type="submit" class="btn btn-primary">افزودن</button>
        </form>
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>نام</th>
                    <th>Slug</th>
                    <th>مقالات</th>
                    <th>عملیات</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($categories as $category)
                    <tr>
                        <td colspan="4">
                            <form method="POST" action="{{ route('admin.categories.update', $category) }}" class="row g-2 align-items-center mb-2">
                                @csrf @method('PUT')
                                <div class="col-md-3">
                                    <input type="text" name="name" value="{{ $category->name }}" class="form-control form-control-sm">
                                </div>
                                <div class="col-md-3">
                                    <input type="text" name="slug" value="{{ $category->slug }}" class="form-control form-control-sm" dir="ltr">
                                </div>
                                <div class="col-md-2 text-muted small">{{ $category->posts_count }} مقاله</div>
                                <div class="col-md-2">
                                    <button type="submit" class="btn btn-sm btn-label-primary">ذخیره</button>
                                </div>
                            </form>
                            <form method="POST" action="{{ route('admin.categories.destroy', $category) }}" onsubmit="return confirm('حذف شود؟')">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-label-danger">حذف</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection
