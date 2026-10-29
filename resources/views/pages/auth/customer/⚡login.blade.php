<?php

declare(strict_types=1);

use App\Services\CustomerAuthService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Iniciar sesión')] #[Layout('layouts.auth.card')] class extends Component
{
    public string $email = '';

    public string $password = '';

    public bool $remember = false;

    public function mount(): void
    {
        if (Auth::guard('customer')->check() || Auth::check()) {
            $this->redirectIntended(default: route('dashboard'), navigate: true);

            return;
        }
    }

    public function login(CustomerAuthService $authService): void
    {
        $this->validate(
            [
                'email' => ['required', 'string', 'email'],
                'password' => ['required', 'string'],
            ],
            [
                'email.required' => __('El correo electrónico es obligatorio.'),
                'email.email' => __('Ingresa un correo electrónico válido.'),
                'password.required' => __('La contraseña es obligatoria.'),
            ],
        );

        try {
            $authService->authenticate(email: $this->email, password: $this->password, remember: $this->remember);
        } catch (ValidationException $e) {
            $this->password = '';

            throw $e;
        }

        $this->redirectIntended(default: route('dashboard'), navigate: true);
    }
};
?>

<div class="flex flex-col gap-5">
    {{-- Header conforming to Design System --}}
    <header class="flex flex-col gap-1.5 mb-1">
        <h1 class="text-2xl sm:text-3xl font-semibold tracking-tight text-text-primary">
            {{ __('Inicia sesión') }}
        </h1>
        <p class="text-xs sm:text-sm text-text-secondary">
            {{ __('Ingresa tu correo electrónico y contraseña.') }}
        </p>
    </header>

    {{-- System / General Alert Banner --}}
    @error('general')
        <div class="flex items-start gap-2.5 rounded-md border border-red-500/25 bg-red-500/10 p-3 text-xs text-red-600 dark:text-red-400"
            role="alert">
            <svg class="size-4 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
            </svg>
            <span class="leading-relaxed font-medium">{{ $message }}</span>
        </div>
    @enderror

    @if (session('status'))
        <div class="flex items-start gap-2.5 rounded-md border border-emerald-500/25 bg-emerald-500/10 p-3 text-xs text-emerald-600 dark:text-emerald-400"
            role="status">
            <span class="leading-relaxed font-medium">{{ session('status') }}</span>
        </div>
    @endif

    @if (session('error'))
        <div class="flex items-start gap-2.5 rounded-md border border-red-500/25 bg-red-500/10 p-3 text-xs text-red-600 dark:text-red-400"
            role="alert">
            <svg class="size-4 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
            </svg>
            <span class="leading-relaxed font-medium">{{ session('error') }}</span>
        </div>
    @endif

    {{-- Customer Login Form --}}
    <form wire:submit="login" class="flex flex-col gap-5">
        {{-- Email Address --}}
        <flux:input wire:model="email" :label="__('Correo electrónico')" type="email" required
            autofocus autocomplete="email" placeholder="tu@correo.com" />

        {{-- Password with Eye Icon Toggle --}}
        <div class="flex flex-col gap-1.5">
            <div class="flex items-center justify-between">
                <label for="password" class="text-xs sm:text-sm font-medium text-text-secondary">
                    {{ __('Contraseña') }}
                </label>
                <a href="{{ route('password.request') }}"
                    class="text-xs sm:text-sm font-medium text-blue-600 dark:text-blue-400 hover:underline"
                    wire:navigate>
                    {{ __('¿Olvidaste tu contraseña?') }}
                </a>
            </div>

            <div class="relative flex items-center" x-data="{ showPassword: false }">
                <input id="password" wire:model="password" :type="showPassword ? 'text' : 'password'" required
                    autocomplete="current-password" placeholder="••••••••"
                    class="h-10 w-full rounded-md border px-3 pr-10 text-sm transition-colors bg-surface-card dark:bg-surface-canvas text-text-primary placeholder:text-slate-400 focus:outline-hidden focus:ring-2 focus:ring-blue-600 focus:border-transparent dark:placeholder:text-slate-500 {{ $errors->has('password') ? 'border-red-500' : 'border-border-strong' }}" />
                <button type="button" @click="showPassword = !showPassword"
                    class="absolute right-0 top-0 bottom-0 flex items-center justify-center w-10 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 transition-colors cursor-pointer"
                    :aria-label="showPassword ? '{{ __('Ocultar contraseña') }}' : '{{ __('Mostrar contraseña') }}'">
                    <flux:icon name="eye" x-show="!showPassword" class="size-4.5" />
                    <flux:icon name="eye-slash" x-show="showPassword" x-cloak class="size-4.5" />
                </button>
            </div>
            @error('password')
                <p class="text-xs text-red-500 font-medium">{{ $message }}</p>
            @enderror
        </div>

        {{-- Remember Me Checkbox --}}
        <flux:checkbox wire:model="remember" :label="__('Recordarme en este dispositivo')" />

        {{-- Primary Submit Button --}}
        <flux:button variant="primary" type="submit" class="w-full h-11 text-base cursor-pointer">
            <span wire:loading.remove wire:target="login">
                {{ __('Iniciar sesión') }}
            </span>
            <span wire:loading wire:target="login" class="inline-flex items-center gap-2">
                <svg class="animate-spin size-4 text-white" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor"
                        d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                    </path>
                </svg>
                <span>{{ __('Iniciando sesión...') }}</span>
            </span>
        </flux:button>
    </form>

    {{-- Social Authentication Divider --}}
    <div class="relative my-1">
        <div class="absolute inset-0 flex items-center">
            <div class="w-full border-t border-border-subtle"></div>
        </div>
        <div class="relative flex justify-center text-xs uppercase">
            <span class="bg-surface-card dark:bg-surface-canvas px-2 text-text-secondary">
                {{ __('O continúa con') }}
            </span>
        </div>
    </div>

    {{-- Google OAuth Button --}}
    <a href="{{ route('auth.google.redirect') }}"
        class="inline-flex h-11 w-full items-center justify-center gap-3 rounded-md border border-border-strong bg-surface-card dark:bg-surface-canvas px-4 text-sm font-medium text-text-primary hover:bg-surface-subtle transition-colors focus:outline-hidden focus:ring-2 focus:ring-blue-600"
        data-test="google-login-button">
        <svg class="size-5 shrink-0" viewBox="0 0 24 24">
            <path fill="#4285F4"
                d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" />
            <path fill="#34A853"
                d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" />
            <path fill="#FBBC05"
                d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.06H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.94l2.85-2.22.81-.63z" />
            <path fill="#EA4335"
                d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.06l3.66 2.84c.87-2.6 3.3-4.52 6.16-4.52z" />
        </svg>
        <span>{{ __('Continuar con Google') }}</span>
    </a>

    {{-- Registration Link --}}
    <div class="pt-3 text-center text-xs sm:text-sm text-text-secondary border-t border-border-subtle">
        {{ __('¿No tienes una cuenta?') }}
        <a href="{{ route('register') }}" class="font-semibold text-blue-600 dark:text-blue-400 hover:underline"
            wire:navigate>
            {{ __('Regístrate como cliente') }}
        </a>
    </div>
</div>
