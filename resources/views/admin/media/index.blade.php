@extends('layouts.admin')

@section('title', 'رسانه')

@section('content')
<div class="mb-4">
    <h4 class="mb-1">مدیریت رسانه</h4>
    <p class="text-muted mb-0">آپلود و مدیریت فایل‌های تصویری</p>
</div>

<div class="card mb-4" style="max-width: 36rem;">
    <div class="card-header"><h5 class="mb-0">آپلود فایل</h5></div>
    <div class="card-body">
        <form method="POST" action="{{ route('admin.media.store') }}" enctype="multipart/form-data">
            @csrf
            <div class="mb-3">
                <label class="form-label">فایل (تصویر یا PDF، حداکثر ۵ مگ)</label>
                <input type="file" name="file" accept="image/*,.pdf" class="form-control" required>
            </div>
            <div class="mb-3">
                <label class="form-label">متن جایگزین (alt)</label>
                <input type="text" name="alt" class="form-control">
            </div>
            <button type="submit" class="btn btn-primary">آپلود</button>
        </form>
    </div>
</div>

<div class="row g-3">
    @foreach ($media as $item)
        <div class="col-sm-6 col-md-4 col-lg-3">
            <div class="card h-100">
                <div class="card-body p-3">
                    @if ($item->isImage())
                        <img src="{{ $item->url() }}" alt="{{ $item->alt }}" class="w-100 rounded mb-2" style="height: 8rem; object-fit: cover;">
                    @else
                        <div class="bg-label-secondary rounded d-flex align-items-center justify-content-center mb-2" style="height: 8rem;">PDF</div>
                    @endif
                    <p class="small text-truncate mb-2" title="{{ $item->filename }}">{{ $item->filename }}</p>
                    <input type="text" readonly value="{{ $item->url() }}" class="form-control form-control-sm mb-2" dir="ltr" onclick="this.select()">
                    <form method="POST" action="{{ route('admin.media.destroy', $item) }}" onsubmit="return confirm('حذف شود؟')">
                        @csrf @method('DELETE')
                        <button type="submit" class="btn btn-sm btn-label-danger w-100">حذف</button>
                    </form>
                </div>
            </div>
        </div>
    @endforeach
</div>

<div class="mt-4">{{ $media->links() }}</div>
@endsection
