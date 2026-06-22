@extends('layouts.admin')

@section('title', 'تنظیمات')

@section('content')
<div class="mb-4">
    <h4 class="mb-1">تنظیمات سایت</h4>
    <p class="text-muted mb-0">اطلاعات تماس و شبکه‌های اجتماعی</p>
</div>

<form method="POST" action="{{ route('admin.settings.update') }}" class="card" style="max-width: 42rem;">
    @csrf
    @method('PUT')
    <div class="card-body">
        <h5 class="mb-3">اطلاعات تماس</h5>
        <div class="mb-3">
            <label class="form-label">ایمیل</label>
            <input type="email" name="contact_email" value="{{ old('contact_email', $settings['contact_email']) }}" class="form-control" required dir="ltr">
        </div>
        <div class="mb-4">
            <label class="form-label">تلفن</label>
            <input type="text" name="contact_phone" value="{{ old('contact_phone', $settings['contact_phone']) }}" class="form-control" dir="ltr">
        </div>

        <hr>
        <h5 class="mb-3">شبکه‌های اجتماعی</h5>
        <div class="mb-3">
            <label class="form-label">LinkedIn</label>
            <input type="url" name="social_linkedin" value="{{ old('social_linkedin', $settings['social_linkedin']) }}" class="form-control" dir="ltr">
        </div>
        <div class="mb-3">
            <label class="form-label">Telegram</label>
            <input type="url" name="social_telegram" value="{{ old('social_telegram', $settings['social_telegram']) }}" class="form-control" dir="ltr">
        </div>
        <div class="mb-4">
            <label class="form-label">Instagram</label>
            <input type="url" name="social_instagram" value="{{ old('social_instagram', $settings['social_instagram']) }}" class="form-control" dir="ltr">
        </div>

        <button type="submit" class="btn btn-primary">ذخیره تنظیمات</button>
    </div>
</form>
@endsection
