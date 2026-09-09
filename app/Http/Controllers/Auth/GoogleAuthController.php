<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Throwable;

class GoogleAuthController extends Controller
{
    public function redirect(): RedirectResponse
    {
        if (! config('services.google.client_id') || ! config('services.google.client_secret')) {
            return redirect('/#google_error=google_not_configured');
        }

        return Socialite::driver('google')->redirect();
    }

    public function callback(): RedirectResponse
    {
        try {
            $googleUser = Socialite::driver('google')->user();
            $email = $googleUser->getEmail();

            if (! $email) {
                return redirect('/#google_error=email_unavailable');
            }

            $user = User::query()->where('google_id', $googleUser->getId())
                ->orWhere('email', $email)
                ->first();

            if ($user) {
                $user->update([
                    'google_id' => $googleUser->getId(),
                    'google_avatar' => $googleUser->getAvatar(),
                    'email_verified_at' => $user->email_verified_at ?: now(),
                ]);
            } else {
                $user = User::create([
                    'name' => $googleUser->getName() ?: Str::before($email, '@'),
                    'email' => $email,
                    'google_id' => $googleUser->getId(),
                    'google_avatar' => $googleUser->getAvatar(),
                    'email_verified_at' => now(),
                    'password' => Hash::make(Str::random(48)),
                ]);
            }

            $token = $user->createToken('andre-shop-google')->plainTextToken;

            return redirect('/#google_token='.rawurlencode($token));
        } catch (Throwable $exception) {
            report($exception);

            return redirect('/#google_error=authentication_failed');
        }
    }
}
