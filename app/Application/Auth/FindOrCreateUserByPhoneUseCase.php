<?php

namespace App\Application\Auth;

use App\Models\User;
use App\Support\PhoneNormalizer;
use App\Support\UserPhoneLookup;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class FindOrCreateUserByPhoneUseCase
{
    public function execute(string $phone): User
    {
        $normalized = PhoneNormalizer::normalize($phone);

        if (! $normalized) {
            throw ValidationException::withMessages([
                'phone' => ['شماره موبایل معتبر نیست.'],
            ]);
        }

        $user = UserPhoneLookup::findByPhone($normalized);

        if ($user) {
            return $user;
        }

        return User::create([
            'name' => 'کاربر '.substr($normalized, -4),
            'email' => $normalized.'@phone.local',
            'phone' => $normalized,
            'password' => Hash::make(Str::random(32)),
        ]);
    }

}
