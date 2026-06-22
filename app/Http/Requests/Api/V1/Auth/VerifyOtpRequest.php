<?php

namespace App\Http\Requests\Api\V1\Auth;

use App\Support\PhoneNormalizer;
use Illuminate\Foundation\Http\FormRequest;

class VerifyOtpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'phone' => PhoneNormalizer::toLatinDigits($this->input('phone')),
            'code' => PhoneNormalizer::normalizeOtpCode($this->input('code')),
        ]);
    }

    public function rules(): array
    {
        $length = config('otp.length', 6);

        return [
            'phone' => ['required', 'string', 'max:20'],
            'code' => ['required', 'string', "size:{$length}"],
        ];
    }

    public function messages(): array
    {
        return [
            'phone.required' => 'شماره موبایل الزامی است.',
            'code.required' => 'کد تأیید الزامی است.',
            'code.size' => 'کد تأیید باید ۶ رقم باشد.',
        ];
    }
}
