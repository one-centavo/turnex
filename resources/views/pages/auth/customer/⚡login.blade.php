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
    public string $identifier = '';

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
                'identifier' => ['required', 'string'],
                'password' => ['required', 'string'],
            ],
            [
                'identifier.required' => __('El correo electrónico o número de celular es obligatorio.'),
                'password.required' => __('La contraseña es obligatoria.'),
            ],
        );

        try {
            $authService->authenticate(identifier: $this->identifier, password: $this->password, remember: $this->remember);
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
        <h1 class="text-2xl sm:text-3xl font-semibold tracking-tight text-[#0F172A] dark:text-[#F9FAFB]">
            {{ __('Inicia sesión') }}
        </h1>
        <p class="text-xs sm:text-sm text-[#64748B] dark:text-[#9CA3AF]">
            {{ __('Ingresa tu correo o celular y contraseña.') }}
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
        {{-- Unified Identifier (Email or Phone) --}}
        <flux:input wire:model="identifier" :label="__('Correo electrónico o celular')" type="text" required
            autofocus autocomplete="username" placeholder="juan@correo.com o 300 123 4567" />

        {{-- Password with Visibility Toggle --}}
        <div class="relative [&_button]:cursor-pointer">
            <flux:input wire:model="password" :label="__('Contraseña')" type="password" required
                autocomplete="current-password" placeholder="••••••••" viewable class="[&_button]:cursor-pointer" />
        </div>

        {{-- Remember Me Checkbox & Forgot Password Link --}}
        <div class="flex items-center justify-between">
            <flux:checkbox wire:model="remember" :label="__('Recordarme')" class="cursor-pointer [&_label]:cursor-pointer [&_ui-checkbox]:cursor-pointer" />

            @if (Route::has('password.request'))
                <a href="{{ route('password.request') }}"
                    class="text-xs font-medium text-blue-600 hover:text-blue-500 hover:underline dark:text-blue-400 focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-blue-600 rounded-sm cursor-pointer"
                    wire:navigate>
                    {{ __('¿Olvidaste tu contraseña?') }}
                </a>
            @endif
        </div>

        {{-- Submit Button --}}
        <flux:button variant="primary" type="submit" class="w-full h-11 text-base font-semibold cursor-pointer"
            wire:loading.attr="disabled">
            <span wire:loading.remove wire:target="login">
                {{ __('Iniciar sesión') }}
            </span>
            <span wire:loading wire:target="login" class="inline-flex items-center gap-2">
                <svg class="size-4 animate-spin" viewBox="0 0 24 24" fill="none">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                        stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor"
                        d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                    </path>
                </svg>
                <span>{{ __('Iniciando sesión...') }}</span>
            </span>
        </flux:button>
    </form>

    {{-- Divider --}}
    <div class="relative flex items-center justify-center my-1">
        <div class="border-t border-[#E2E8F0] dark:border-[#334155] w-full"></div>
        <span class="bg-white dark:bg-[#1E293B] px-3 text-xs text-[#64748B] dark:text-[#9CA3AF] uppercase font-medium tracking-wider">
            {{ __('o') }}
        </span>
        <div class="border-t border-[#E2E8F0] dark:border-[#334155] w-full"></div>
    </div>

    {{-- Google OAuth Button --}}
    <a href="{{ route('auth.google.redirect') }}"
        class="w-full h-11 px-4 rounded-md border border-[#CBD5E1] dark:border-[#334155] bg-white dark:bg-[#1E293B] hover:bg-slate-50 dark:hover:bg-slate-800 text-[#0F172A] dark:text-[#F9FAFB] font-medium text-sm sm:text-base flex items-center justify-center gap-3 transition-colors cursor-pointer shadow-xs focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-blue-600">
        <svg class="size-5 shrink-0" viewBox="0 0 24 24">
            <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" />
            <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" />
            <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.06H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.94l2.85-2.22.81-.63z" />
            <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.06l3.66 2.84c.87-2.6 3.3-4.52 6.16-4.52z" />
        </svg>
        <span>{{ __('Continuar con Google') }}</span>
    </a>

    {{-- Registration CTA Link conforming to Design System --}}
    <div class="mt-2 text-center text-xs sm:text-sm text-[#64748B] dark:text-[#9CA3AF]">
        <span>{{ __('¿No tienes una cuenta?') }}</span>
        <a href="{{ route('register') }}"
            class="font-medium text-blue-600 hover:text-blue-500 hover:underline dark:text-blue-400 focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-blue-600 rounded-sm ms-1"
            wire:navigate>
            {{ __('Regístrate') }}
        </a>
    </div>
</div>
