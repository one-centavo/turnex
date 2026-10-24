<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\CustomerAuthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirectResponse;
use Throwable;

class CustomerGoogleAuthController extends Controller
{
    /**
     * User-facing error message displayed when Google authentication fails or is cancelled.
     */
    public const string ERROR_MESSAGE = 'No se pudo completar la autenticación con Google. Por favor, intenta de nuevo.';

    public function __construct(
        public CustomerAuthService $authService,
    ) {}

    /**
     * Redirect the customer to Google's OAuth 2.0 authorization screen.
     */
    public function redirect(): SymfonyRedirectResponse
    {
        if (Auth::guard('customer')->check() || Auth::check()) {
            return redirect()->intended(route('dashboard'));
        }

        return Socialite::driver('google')->redirect();
    }

    /**
     * Handle the incoming callback response from Google OAuth.
     */
    public function callback(): RedirectResponse
    {
        if (Auth::guard('customer')->check() || Auth::check()) {
            return redirect()->intended(route('dashboard'));
        }

        try {
            /** @var SocialiteUser $googleUser */
            $googleUser = Socialite::driver('google')->user();

            $this->authService->handleGoogleUser($googleUser);

            return redirect()->intended(route('dashboard'));
        } catch (Throwable $e) {
            report($e);

            return redirect()->route('login')->with('error', __(self::ERROR_MESSAGE));
        }
    }
}
